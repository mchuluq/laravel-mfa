<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Webauthn\Server;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
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
            $server = $this->getServer();
            $server->timeout = $this->config['timeout'] ?? 60000;
            $userEntity = $this->getUserEntity($user);
            $excludeCredentials = $this->getExistingCredentialDescriptors($user);
            $criteria = new AuthenticatorSelectionCriteria(
                $this->config['authenticator_attachment'] ?? null,
                $this->config['require_resident_key'] ?? false,
                $this->config['user_verification'] ?? 'preferred'
            );
            $creationOptions = $server->generatePublicKeyCredentialCreationOptions(
                $userEntity,
                $this->config['attestation'] ?? 'none',
                $excludeCredentials,
                $criteria
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
            $server = $this->getServer();
            $userEntity = $this->getUserEntity($user);
            // This performs full WebAuthn assertion verification: challenge match,
            // origin/rpId check, user presence/verification flags, signature
            // verification against the stored public key, and counter replay check.
            $publicKeyCredentialSource = $server->loadAndCheckAssertionResponse(
                json_encode($credentialData),
                $requestOptions,
                $userEntity,
                $this->getPsrRequest()
            );
            // Defense in depth: the credential must belong to the user being verified.
            if ((string) $publicKeyCredentialSource->getUserHandle() !== (string) $user->getAuthIdentifier()) {
                throw MFAException::webAuthnError('Credential does not belong to this user.');
            }
            session()->forget([$this->requestOptionsKey]);
            $webAuthnKey = WebAuthnKey::where('user_id', $user->getAuthIdentifier())
                ->where('credential_id', Base64Url::encode($publicKeyCredentialSource->getPublicKeyCredentialId()))
                ->first();
            if ($webAuthnKey) {
                $webAuthnKey->markAsUsed();
            }
            $this->clearRateLimit($user);
            $this->updateLastUsed($user);
            $this->fireEvent(WebAuthnVerified::class, [
                'user' => $user,
                'driver' => $this->name,
                'key_id' => $webAuthnKey->id ?? null,
            ]);
            $this->log('WebAuthn verification successful', [
                'user_id' => $user->getAuthIdentifier(),
                'key_id' => $webAuthnKey->id ?? null,
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
            $server = $this->getServer();
            $server->timeout = $this->config['timeout'] ?? 60000;
            $allowCredentials = $this->getExistingCredentialDescriptors($user);
            $requestOptions = $server->generatePublicKeyCredentialRequestOptions(
                $this->config['user_verification'] ?? 'preferred',
                $allowCredentials
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
            $server = $this->getServer();
            // Full attestation verification: challenge match, origin/rpId check,
            // attestation statement validity, and that the credential ID is new.
            $publicKeyCredentialSource = $server->loadAndCheckAttestationResponse(
                json_encode($credential),
                $creationOptions,
                $this->getPsrRequest()
            );
        } catch (Throwable $e) {
            $this->log('WebAuthn registration failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MFAException::webAuthnError($e->getMessage());
        }
        $webAuthnKey = WebAuthnKey::create([
            'user_id' => $user->getAuthIdentifier(),
            'name' => $name ?: 'Security Key',
            'credential_id' => Base64Url::encode($publicKeyCredentialSource->getPublicKeyCredentialId()),
            'public_key' => Base64Url::encode($publicKeyCredentialSource->getCredentialPublicKey()),
            'aaguid' => $publicKeyCredentialSource->getAaguid()->toString(),
            'counter' => $publicKeyCredentialSource->getCounter(),
            'transports' => $publicKeyCredentialSource->getTransports(),
            'attestation_format' => $publicKeyCredentialSource->getAttestationType(),
        ]);
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
     * Build a WebAuthn server instance bound to this app's relying party and
     * to the Eloquent-backed credential source repository.
     *
     * @return Server
     */
    protected function getServer(): Server{
        return new Server($this->getRelyingPartyEntity(), new EloquentCredentialSourceRepository());
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
     * @param mixed $options
     * @return array
     */
    protected function encodeOptions($options): array{
        return json_decode(json_encode($options), true);
    }
}
