<?php namespace Mchuluq\LaravelMFA\WebAuthn;

use Base64Url\Base64Url;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;
use Mchuluq\LaravelMFA\Models\WebAuthnKey;

/**
 * Maps between the webauthn_keys Eloquent model and webauthn-lib's CredentialRecord
 * value object. webauthn-lib v5 removed the PublicKeyCredentialSourceRepository
 * interface (and the Server class that consumed it) in favor of callers looking up
 * and persisting credentials themselves, so this class is now a plain mapper called
 * directly by WebAuthnDriver instead of an interface implementation.
 *
 * attestationType/trustPath are not persisted: they are only meaningful during
 * the one-off attestation check performed at registration time and are not
 * needed to verify subsequent assertions (signature + counter checks only
 * need the aaguid, public key and counter).
 */
class EloquentCredentialSourceRepository{

    public function findByCredentialId(string $userId, string $rawCredentialId): ?WebAuthnKey{
        return WebAuthnKey::where('user_id', $userId)
            ->where('credential_id', Base64Url::encode($rawCredentialId))
            ->first();
    }

    public function toCredentialRecord(WebAuthnKey $key): CredentialRecord{
        return new CredentialRecord(
            Base64Url::decode($key->credential_id),
            'public-key',
            $key->transports ?? [],
            $key->attestation_format ?: 'none',
            EmptyTrustPath::create(),
            Uuid::fromString($key->aaguid ?: '00000000-0000-0000-0000-000000000000'),
            Base64Url::decode($key->public_key),
            (string) $key->user_id,
            (int) $key->counter,
            null,
            $key->backup_eligible,
            $key->backup_status,
            $key->uv_initialized
        );
    }

    public function persistFromCredentialRecord(CredentialRecord $credentialRecord, string $userId, ?string $name = null): WebAuthnKey{
        return WebAuthnKey::updateOrCreate(
            ['credential_id' => Base64Url::encode($credentialRecord->publicKeyCredentialId)],
            [
                'user_id' => $userId,
                'name' => $name ?: 'Security Key',
                'public_key' => Base64Url::encode($credentialRecord->credentialPublicKey),
                'aaguid' => $credentialRecord->aaguid->toRfc4122(),
                'counter' => $credentialRecord->counter,
                'transports' => $credentialRecord->transports,
                'attestation_format' => $credentialRecord->attestationType,
                'backup_eligible' => $credentialRecord->backupEligible,
                'backup_status' => $credentialRecord->backupStatus,
                'uv_initialized' => $credentialRecord->uvInitialized,
            ]
        );
    }

    public function updateFromCredentialRecord(WebAuthnKey $key, CredentialRecord $credentialRecord): void{
        $key->update([
            'counter' => $credentialRecord->counter,
            'backup_eligible' => $credentialRecord->backupEligible,
            'backup_status' => $credentialRecord->backupStatus,
            'uv_initialized' => $credentialRecord->uvInitialized,
        ]);
    }
}
