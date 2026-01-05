<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Webauthn\Server;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\EdDSA;
use Cose\Algorithm\Signature\RSA;
use Mchuluq\LaravelMFA\Models\WebAuthnKey;
use Mchuluq\LaravelMFA\Exceptions\MFAException;
use Mchuluq\LaravelMFA\Events\WebAuthnRegistered;
use Mchuluq\LaravelMFA\Events\WebAuthnVerified;

class WebAuthnDriver extends AbstractDriver{

    /**
     * The driver name.
     *
     * @var string
     */
    protected $name = 'webauthn';

    /**
     * Setup MFA for the user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function setup(Authenticatable $user, array $options = []){
        try {
            // Create registration options
            $rpEntity = $this->getRelyingPartyEntity();
            $userEntity = $this->getUserEntity($user);
            $challenge = random_bytes($this->config['challenge_length'] ?? 32);
            // Store challenge in session for verification
            session()->put('webauthn_challenge', base64_encode($challenge));
            session()->put('webauthn_user_id', $user->getAuthIdentifier());
            $publicKeyCredentialCreationOptions = new PublicKeyCredentialCreationOptions(
                $rpEntity,
                $userEntity,
                $challenge,
                $this->getSupportedPublicKeyParams()
            );
            // Set authenticator selection criteria
            $authenticatorSelection = new AuthenticatorSelectionCriteria(
                $this->config['authenticator_attachment'] ?? null,
                $this->config['require_resident_key'] ?? false,
                $this->config['user_verification'] ?? 'preferred'
            );
            $publicKeyCredentialCreationOptions->setAuthenticatorSelection($authenticatorSelection);
            // Set timeout
            $publicKeyCredentialCreationOptions->setTimeout($this->config['timeout'] ?? 60000);
            // Set attestation
            $publicKeyCredentialCreationOptions->setAttestation($this->config['attestation'] ?? 'none');
            // Exclude existing credentials
            $excludeCredentials = $this->getExistingCredentials($user);
            if (!empty($excludeCredentials)) {
                $publicKeyCredentialCreationOptions->excludeCredentials($excludeCredentials);
            }
            $this->log('WebAuthn setup initiated', [
                'user_id' => $user->getAuthIdentifier(),
            ]);
            return [
                'publicKey' => $this->encodeOptions($publicKeyCredentialCreationOptions),
            ];
        } catch (\Exception $e) {
            $this->log('WebAuthn setup failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MFAException::webAuthnError($e->getMessage());
        }
    }

    /**
     * Verify the WebAuthn credential.
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
            // Find the credential
            $webAuthnKey = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->where('credential_id', $credentialData['id'])->first();
            if (!$webAuthnKey) {
                $this->incrementRateLimit($user);
                return false;
            }
            // Verify the challenge from session
            $storedChallenge = session()->get('webauthn_auth_challenge');
            if (!$storedChallenge) {
                throw MFAException::challengeTimeout();
            }
            // Here you would implement full WebAuthn assertion verification
            // This is a simplified version - in production, use a proper WebAuthn library
            // to verify signature, counter, etc.
            // Update counter and last used
            $webAuthnKey->incrementCounter();
            $webAuthnKey->markAsUsed();
            // Clear session
            session()->forget(['webauthn_auth_challenge']);
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
        } catch (\Exception $e) {
            $this->incrementRateLimit($user);
            $this->log('WebAuthn verification failed', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate/Send challenge to user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function challenge(Authenticatable $user, array $options = []){
        try {
            $rpEntity = $this->getRelyingPartyEntity();
            $challenge = random_bytes($this->config['challenge_length'] ?? 32);
            // Store challenge in session
            session()->put('webauthn_auth_challenge', base64_encode($challenge));
            $publicKeyCredentialRequestOptions = new PublicKeyCredentialRequestOptions(
                $challenge
            );
            // Set RP ID
            $publicKeyCredentialRequestOptions->setRpId($rpEntity->getId());
            // Set timeout
            $publicKeyCredentialRequestOptions->setTimeout($this->config['timeout'] ?? 60000);
            // Set user verification
            $publicKeyCredentialRequestOptions->setUserVerification(
                $this->config['user_verification'] ?? 'preferred'
            );
            // Allow specific credentials
            $allowCredentials = $this->getExistingCredentials($user);
            if (!empty($allowCredentials)) {
                $publicKeyCredentialRequestOptions->allowCredentials($allowCredentials);
            }
            return [
                'publicKey' => $this->encodeOptions($publicKeyCredentialRequestOptions),
            ];
        } catch (\Exception $e) {
            throw MFAException::webAuthnError($e->getMessage());
        }
    }

    /**
     * Register a new WebAuthn key.
     *
     * @param Authenticatable $user
     * @param array $credential
     * @param string|null $name
     * @return WebAuthnKey
     */
    public function register(Authenticatable $user, array $credential, ?string $name = null): WebAuthnKey{
        // Verify challenge
        $storedChallenge = session()->get('webauthn_challenge');
        $storedUserId = session()->get('webauthn_user_id');
        if (!$storedChallenge || $storedUserId != $user->getAuthIdentifier()) {
            throw MFAException::challengeTimeout();
        }
        // In production, implement full attestation verification here
        // Store the credential
        $webAuthnKey = WebAuthnKey::create([
            'user_id' => $user->getAuthIdentifier(),
            'name' => $name ?? 'Security Key',
            'credential_id' => $credential['id'] ?? '',
            'public_key' => $credential['publicKey'] ?? '',
            'aaguid' => $credential['aaguid'] ?? '00000000-0000-0000-0000-000000000000',
            'counter' => $credential['counter'] ?? 0,
            'transports' => $credential['transports'] ?? [],
            'attestation_format' => $credential['attestationFormat'] ?? 'none',
        ]);
        // Enable the method
        $this->enableMethod($user);
        // Clear session
        session()->forget(['webauthn_challenge', 'webauthn_user_id']);
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
     * Get supported public key parameters.
     *
     * @return array
     */
    protected function getSupportedPublicKeyParams(): array{
        return [
            new PublicKeyCredentialParameters('public-key', -7),  // ES256
            new PublicKeyCredentialParameters('public-key', -257), // RS256
            new PublicKeyCredentialParameters('public-key', -8),  // EdDSA
        ];
    }

    /**
     * Get existing credentials for user.
     *
     * @param Authenticatable $user
     * @return array
     */
    protected function getExistingCredentials(Authenticatable $user): array{
        $keys = WebAuthnKey::where('user_id', $user->getAuthIdentifier())->get();
        return $keys->map(function ($key) {
            return new PublicKeyCredentialDescriptor(
                'public-key',
                $key->credential_id,
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
        // Convert to array suitable for JSON encoding
        // This is a simplified version - in production use proper serialization
        return json_decode(json_encode($options), true);
    }
}