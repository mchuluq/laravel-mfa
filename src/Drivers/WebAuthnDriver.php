<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Base64Url\Base64Url;
use Mchuluq\LaravelMFA\Models\WebAuthnKey;
use Mchuluq\LaravelMFA\Exceptions\MFAException;
use Mchuluq\LaravelMFA\Events\WebAuthnRegistered;
use Mchuluq\LaravelMFA\Events\WebAuthnVerified;
use Mchuluq\LaravelMFA\WebAuthn\EloquentCredentialSourceRepository;

class WebAuthnDriver extends AbstractDriver{

    /**
     * The driver name.
     *
     * @var string
     */
    protected $name = 'webauthn';

    /**
     * Session key holding the serialized creation options for a pending registration.
     *
     * @var string
     */
    protected $creationOptionsKey = 'webauthn_creation_options';

    /**
     * Session key holding the serialized request options for a pending assertion.
     *
     * @var string
     */
    protected $requestOptionsKey = 'webauthn_auth_options';

    /**
     * Setup MFA for the user (generate registration/creation options).
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function setup(Authenticatable $user, array $options = []){
        try {
            $userEntity = $this->getUserEntity($user);
            $excludeCredentials = $this->getExistingCredentialDescriptors($user);
            $criteria = new AuthenticatorSelectionCriteria(
                $this->config['authenticator_attachment'] ?? null,
                $this->config['user_verification'] ?? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                ($this->config['require_resident_key'] ?? false)
                    ? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
                    : AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_NO_PREFERENCE
            );
            $creationOptions = new PublicKeyCredentialCreationOptions(
                $this->getRelyingPartyEntity(),
                $userEntity,
                random_bytes(32),
                $this->getPublicKeyCredentialParameters(),
                $criteria,
                $this->config['attestation'] ?? PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
                $excludeCredentials,
                $this->config['timeout'] ?? 60000
            );
            // Store the exact options object used to generate the challenge, so the
            // attestation can be verified against the very same challenge/params later.
            session()->put($this->creationOptionsKey, serialize($creationOptions));
            session()->put('webauthn_user_id', $user->getAuthIdentifier());
            $this->log('WebAuthn setup initiated', [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return [
                'publicKey' => $this->encodeOptions($creationOptions),
            ];
        } catch (Throwable $e) {
            $this->log('WebAuthn setup failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MFAException::webAuthnError($e->getMessage());
        }
    }

    /**
     * Verify the WebAuthn credential (assertion) against the stored public key.
     *
     * @param Authenticatable $user
     * @param mixed $credential
     * @param array $options
     * @return bool
     */
    public function verify(Authenticatable $user, $credential, array $options = []): bool{
        $this->checkRateLimit($user);
        try {
            $credentialData = is_string($credential) ? json_decode($credential, true) : $credential;
            if (!$credentialData || !isset($credentialData['id'])) {
                $this->incrementRateLimit($user);
                return false;
            }
            $storedOptions = session()->get($this->requestOptionsKey);
            if (!$storedOptions) {
                throw MFAException::challengeTimeout();
            }
            /** @var PublicKeyCredentialRequestOptions $requestOptions */
            $requestOptions = unserialize($storedOptions, ['allowed_classes' => true]);

            $webAuthnKey = $this->getCredentialRepository()->findByCredentialId(
                (string) $user->getAuthIdentifier(),
                Base64Url::decode($credentialData['id'])
            );
            if (!$webAuthnKey) {
                $this->incrementRateLimit($user);
                return false;
            }

            $publicKeyCredential = $this->getSerializer()->deserialize(
                json_encode($credentialData),
                PublicKeyCredential::class,
                'json'
            );
            if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
                $this->incrementRateLimit($user);
                return false;
            }

            $credentialRecord = $this->getCredentialRepository()->toCredentialRecord($webAuthnKey);
            $validator = AuthenticatorAssertionResponseValidator::create(
                $this->getCeremonyStepManagerFactory()->requestCeremony()
            );
            // This performs full WebAuthn assertion verification: challenge match,
            // origin/rpId check, user presence/verification flags, signature
            // verification against the stored public key, and counter replay check.
            $credentialRecord = $validator->check(
                $credentialRecord,
                $publicKeyCredential->response,
                $requestOptions,
                $this->getRelyingPartyEntity()->id,
                (string) $user->getAuthIdentifier()
            );
            // Defense in depth: the credential must belong to the user being verified.
            if ((string) $credentialRecord->userHandle !== (string) $user->getAuthIdentifier()) {
                throw MFAException::webAuthnError('Credential does not belong to this user.');
            }
            session()->forget([$this->requestOptionsKey]);
            $this->getCredentialRepository()->updateFromCredentialRecord($webAuthnKey, $credentialRecord);
            $webAuthnKey->markAsUsed();
            $this->clearRateLimit($user);
            $this->updateLastUsed($user);
            $this->fireEvent(WebAuthnVerified::class, [
                'user' => $user,
                'driver' => $this->name,
                'key_id' => $webAuthnKey->id,
            ]);
            $this->log('WebAuthn verification successful', [
                'user_id' => $user->getAuthIdentifier(),
                'key_id' => $webAuthnKey->id,
            ]);
            return true;
        } catch (Throwable $e) {
            $this->incrementRateLimit($user);
            $this->log('WebAuthn verification failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate/Send challenge to user (assertion/request options).
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function challenge(Authenticatable $user, array $options = []){
        try {
            $allowCredentials = $this->getExistingCredentialDescriptors($user);
            $requestOptions = new PublicKeyCredentialRequestOptions(
                random_bytes(32),
                $this->getRelyingPartyEntity()->id,
                $allowCredentials,
                $this->config['user_verification'] ?? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                $this->config['timeout'] ?? 60000
            );
            session()->put($this->requestOptionsKey, serialize($requestOptions));
            return [
                'publicKey' => $this->encodeOptions($requestOptions),
            ];
        } catch (Throwable $e) {
            throw MFAException::webAuthnError($e->getMessage());
        }
    }

    /**
     * Register a new WebAuthn key (verify attestation and persist the credential).
     *
     * @param Authenticatable $user
     * @param array $credential
     * @param string|null $name
     * @return WebAuthnKey
     */
    public function register(Authenticatable $user, array $credential, ?string $name = null): WebAuthnKey{
        $storedOptions = session()->get($this->creationOptionsKey);
        $storedUserId = session()->get('webauthn_user_id');
        if (!$storedOptions || (string) $storedUserId !== (string) $user->getAuthIdentifier()) {
            throw MFAException::challengeTimeout();
        }
        /** @var PublicKeyCredentialCreationOptions $creationOptions */
        $creationOptions = unserialize($storedOptions, ['allowed_classes' => true]);
        try {
            $publicKeyCredential = $this->getSerializer()->deserialize(
                json_encode($credential),
                PublicKeyCredential::class,
                'json'
            );
            if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
                throw MFAException::webAuthnError('Invalid attestation response.');
            }
            $validator = AuthenticatorAttestationResponseValidator::create(
                $this->getCeremonyStepManagerFactory()->creationCeremony()
            );
            // Full attestation verification: challenge match, origin/rpId check,
            // attestation statement validity, and that the credential ID is new.
            $credentialRecord = $validator->check(
                $publicKeyCredential->response,
                $creationOptions,
                $this->getRelyingPartyEntity()->id
            );
        } catch (Throwable $e) {
            $this->log('WebAuthn registration failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MFAException::webAuthnError($e->getMessage());
        }
        $webAuthnKey = $this->getCredentialRepository()->persistFromCredentialRecord(
            $credentialRecord,
            (string) $user->getAuthIdentifier(),
            $name
        );
        // Enable the method
        $this->enableMethod($user);
        // Clear session
        session()->forget([$this->creationOptionsKey, 'webauthn_user_id']);
        $this->fireEvent(WebAuthnRegistered::class, [
            'user' => $user,
            'driver' => $this->name,
            'key_id' => $webAuthnKey->id,
        ]);
        $this->log('WebAuthn key registered', [
            'user_id' => $user->getAuthIdentifier(),
            'key_id' => $webAuthnKey->id,
        ]);
        return $webAuthnKey;
    }

    /**
     * Disable MFA for the user.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function disable(Authenticatable $user): bool{
        parent::disable($user);
        // Delete all keys
        $deleted = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->delete();
        $this->log('WebAuthn disabled', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return $deleted > 0;
    }

    /**
     * Delete a specific key.
     *
     * @param Authenticatable $user
     * @param int $keyId
     * @return bool
     */
    public function deleteKey(Authenticatable $user, int $keyId): bool{
        $key = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->where('id', $keyId)->first();
        if (!$key) {
            return false;
        }
        // Check if this is the only key
        $keysCount = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->count();
        if ($keysCount === 1 && $this->isOnlyMethod($user)) {
            throw MFAException::cannotDisableLastMethod();
        }
        return $key->delete();
    }

    /**
     * Get driver-specific data for the user.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getData(Authenticatable $user){
        $keys = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->get();
        return [
            'keys' => $keys->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'authenticator_type' => $key->authenticator_type,
                    'transports' => $key->transports_string,
                    'last_used_at' => $key->last_used_at,
                    'created_at' => $key->created_at,
                ];
            }),
            'keys_count' => $keys->count(),
        ];
    }

    /**
     * Validate setup data.
     *
     * @param array $data
     * @return array
     */
    public function validateSetup(array $data): array{
        return validator($data, [
            'name' => 'nullable|string|max:100',
            'credential' => 'required|array',
        ])->validate();
    }

    /**
     * Validate verification data.
     *
     * @param array $data
     * @return array
     */
    public function validateVerification(array $data): array{
        return validator($data, [
            'credential' => 'required',
        ])->validate();
    }

    /**
     * Mapper between the Eloquent-backed WebAuthnKey model and webauthn-lib's
     * CredentialRecord value object.
     *
     * @return EloquentCredentialSourceRepository
     */
    protected function getCredentialRepository(): EloquentCredentialSourceRepository{
        return new EloquentCredentialSourceRepository();
    }

    /**
     * Build the Symfony serializer webauthn-lib uses to turn the raw JSON credential
     * sent by the browser into PublicKeyCredential/AuthenticatorResponse objects.
     *
     * @return \Symfony\Component\Serializer\SerializerInterface
     */
    protected function getSerializer(){
        return (new WebauthnSerializerFactory($this->getAttestationStatementSupportManager()))->create();
    }

    /**
     * Build the ceremony step pipeline (challenge, origin, signature, counter, ...
     * checks) for both the registration and authentication ceremonies, scoped to
     * this app's own origin only.
     *
     * @return CeremonyStepManagerFactory
     */
    protected function getCeremonyStepManagerFactory(): CeremonyStepManagerFactory{
        $factory = new CeremonyStepManagerFactory();
        $factory->setAttestationStatementSupportManager($this->getAttestationStatementSupportManager());
        $factory->setAllowedOrigins([rtrim(config('app.url'), '/')]);
        return $factory;
    }

    /**
     * @return AttestationStatementSupportManager
     */
    protected function getAttestationStatementSupportManager(): AttestationStatementSupportManager{
        return new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);
    }

    /**
     * The public key algorithms this relying party accepts (ES256/RS256, the two
     * most broadly supported COSE algorithms across platform/roaming authenticators).
     *
     * @return PublicKeyCredentialParameters[]
     */
    protected function getPublicKeyCredentialParameters(): array{
        return [
            PublicKeyCredentialParameters::createPk(ES256::ID),
            PublicKeyCredentialParameters::createPk(RS256::ID),
        ];
    }

    /**
     * Build a minimal PSR-7 request for the webauthn-lib validators. Only the
     * URI/host is used (for origin/rpId checks); nothing else is required
     * since token binding is not enforced (IgnoreTokenBindingHandler).
     *
     * @return ServerRequestInterface
     */
    protected function getPsrRequest(): ServerRequestInterface{
        $request = request();
        return new ServerRequest($request->method(), $request->fullUrl());
    }

    /**
     * Get relying party entity.
     *
     * @return PublicKeyCredentialRpEntity
     */
    protected function getRelyingPartyEntity(): PublicKeyCredentialRpEntity{
        $name = config('app.name', 'Laravel App');
        $id = parse_url(config('app.url'), PHP_URL_HOST);
        return new PublicKeyCredentialRpEntity($name, $id);
    }

    /**
     * Get user entity.
     *
     * @param Authenticatable $user
     * @return PublicKeyCredentialUserEntity
     */
    protected function getUserEntity(Authenticatable $user): PublicKeyCredentialUserEntity{
        $id = (string) $user->getAuthIdentifier();
        $name = $user->email ?? $id;
        $displayName = $user->name ?? $name;
        return new PublicKeyCredentialUserEntity($name, $id, $displayName);
    }

    /**
     * Get existing credential descriptors for user (raw credential IDs, as
     * required by PublicKeyCredentialDescriptor/the webauthn-lib validators).
     *
     * @param Authenticatable $user
     * @return PublicKeyCredentialDescriptor[]
     */
    protected function getExistingCredentialDescriptors(Authenticatable $user): array{
        $keys = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->get();
        return $keys->map(function ($key) {
            return new PublicKeyCredentialDescriptor(
                'public-key',
                Base64Url::decode($key->credential_id),
                $key->transports ?? []
            );
        })->toArray();
    }

    /**
     * Encode options to JSON-safe array.
     *
     * The options objects (PublicKeyCredentialCreationOptions/RequestOptions) carry
     * raw binary in `challenge` (and user id, credential ids). A plain json_encode()
     * fails with "Malformed UTF-8 characters" on that binary, so this must go through
     * the webauthn-lib serializer, which base64url-encodes those fields the way the
     * browser's PublicKeyCredential.parseCreationOptionsFromJSON()/parseRequestOptionsFromJSON()
     * expect.
     *
     * @param mixed $options
     * @return array
     */
    protected function encodeOptions($options): array{
        return json_decode($this->getSerializer()->serialize($options, 'json'), true);
    }
}
