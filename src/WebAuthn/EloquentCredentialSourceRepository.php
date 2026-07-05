<?php namespace Mchuluq\LaravelMFA\WebAuthn;

use Base64Url\Base64Url;
use Ramsey\Uuid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;
use Mchuluq\LaravelMFA\Models\WebAuthnKey;

/**
 * Persists WebAuthn credential sources to the mfa_webauthn_keys table.
 *
 * attestationType/trustPath are not persisted: they are only meaningful during
 * the one-off attestation check performed at registration time and are not
 * needed to verify subsequent assertions (signature + counter checks only
 * need the aaguid, public key and counter).
 */
class EloquentCredentialSourceRepository implements PublicKeyCredentialSourceRepository{

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource{
        $key = WebAuthnKey::where('credential_id', Base64Url::encode($publicKeyCredentialId))->first();
        return $key ? $this->toCredentialSource($key) : null;
    }

    /**
     * @return PublicKeyCredentialSource[]
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array{
        return WebAuthnKey::where('user_id', $publicKeyCredentialUserEntity->getId())
            ->get()
            ->map(function (WebAuthnKey $key) {
                return $this->toCredentialSource($key);
            })
            ->all();
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void{
        WebAuthnKey::updateOrCreate(
            ['credential_id' => Base64Url::encode($publicKeyCredentialSource->getPublicKeyCredentialId())],
            [
                'user_id' => $publicKeyCredentialSource->getUserHandle(),
                'public_key' => Base64Url::encode($publicKeyCredentialSource->getCredentialPublicKey()),
                'aaguid' => $publicKeyCredentialSource->getAaguid()->toString(),
                'counter' => $publicKeyCredentialSource->getCounter(),
                'transports' => $publicKeyCredentialSource->getTransports(),
            ]
        );
    }

    protected function toCredentialSource(WebAuthnKey $key): PublicKeyCredentialSource{
        return new PublicKeyCredentialSource(
            Base64Url::decode($key->credential_id),
            'public-key',
            $key->transports ?? [],
            'none',
            new EmptyTrustPath(),
            Uuid::fromString($key->aaguid ?: '00000000-0000-0000-0000-000000000000'),
            Base64Url::decode($key->public_key),
            (string) $key->user_id,
            (int) $key->counter
        );
    }
}
