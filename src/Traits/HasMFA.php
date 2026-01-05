<?php namespace Mchuluq\LaravelMFA\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Mchuluq\LaravelMFA\Models\MFAMethod;
use Mchuluq\LaravelMFA\Models\TOTPSecret;
use Mchuluq\LaravelMFA\Models\EmailOTP;
use Mchuluq\LaravelMFA\Models\WebAuthnKey;

trait HasMFA{
    /**
     * Get all MFA methods for the user.
     *
     * @return HasMany
     */
    public function mfaMethods(): HasMany{
        return $this->hasMany(MFAMethod::class, 'user_id');
    }

    /**
     * Get the TOTP secret for the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function totpSecret(){
        return $this->hasOne(TOTPSecret::class, 'user_id');
    }

    /**
     * Get email OTPs for the user.
     *
     * @return HasMany
     */
    public function emailOtps(): HasMany{
        return $this->hasMany(EmailOTP::class, 'user_id');
    }

    /**
     * Get WebAuthn keys for the user.
     *
     * @return HasMany
     */
    public function webAuthnKeys(): HasMany{
        return $this->hasMany(WebAuthnKey::class, 'user_id');
    }

    /**
     * Check if user has MFA enabled.
     *
     * @return bool
     */
    public function hasMFAEnabled(): bool{
        return $this->mfaMethods()->where('is_enabled', true)->exists();
    }

    /**
     * Check if user has a specific MFA method enabled.
     *
     * @param string $driver
     * @return bool
     */
    public function hasMFAMethod(string $driver): bool{
        return $this->mfaMethods()->where('driver', $driver)->where('is_enabled', true)->exists();
    }

    /**
     * Get enabled MFA methods.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEnabledMFAMethods(){
        return $this->mfaMethods()->where('is_enabled', true)->get();
    }

    /**
     * Get all MFA methods (enabled and disabled).
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMFAMethods(){
        return $this->mfaMethods()->get();
    }

    /**
     * Get primary MFA method.
     *
     * @return MFAMethod|null
     */
    public function getPrimaryMFAMethod(): ?MFAMethod{
        return $this->mfaMethods()->where('is_primary', true)->where('is_enabled', true)->first();
    }

    /**
     * Enable MFA method.
     *
     * @param string $driver
     * @param array $options
     * @return mixed
     */
    public function enableMFA(string $driver, array $options = []){
        return mfa($driver)->setup($this, $options);
    }

    /**
     * Disable MFA method.
     *
     * @param string $driver
     * @return bool
     */
    public function disableMFA(string $driver): bool{
        return mfa($driver)->disable($this);
    }

    /**
     * Disable all MFA methods.
     *
     * @return bool
     */
    public function disableAllMFA(): bool{
        return mfa()->disableAll($this);
    }

    /**
     * Verify MFA code.
     *
     * @param string $driver
     * @param mixed $credential
     * @param array $options
     * @return bool
     */
    public function verifyMFA(string $driver, $credential, array $options = []): bool{
        return mfa($driver)->verify($this, $credential, $options);
    }

    /**
     * Set primary MFA method.
     *
     * @param string $driver
     * @return bool
     */
    public function setPrimaryMFAMethod(string $driver): bool{
        return mfa()->setPrimaryMethod($this, $driver);
    }

    /**
     * Get MFA statistics.
     *
     * @return array
     */
    public function getMFAStatistics(): array{
        return mfa()->getStatistics($this);
    }

    /**
     * Check if user requires MFA verification.
     *
     * @return bool
     */
    public function requiresMFA(): bool{
        return mfa()->requiresMFA($this);
    }

    /**
     * Get TOTP backup codes.
     *
     * @return array
     */
    public function getTOTPBackupCodes(): array{
        $secret = $this->totpSecret;        
        if (!$secret) {
            return [];
        }
        return $secret->decrypted_backup_codes;
    }

    /**
     * Check if user has backup codes.
     *
     * @return bool
     */
    public function hasBackupCodes(): bool{
        $secret = $this->totpSecret;        
        if (!$secret) {
            return false;
        }
        return $secret->remaining_backup_codes > 0;
    }

    /**
     * Get remaining backup codes count.
     *
     * @return int
     */
    public function getRemainingBackupCodesCount(): int{
        $secret = $this->totpSecret;        
        if (!$secret) {
            return 0;
        }
        return $secret->remaining_backup_codes;
    }

    /**
     * Regenerate TOTP backup codes.
     *
     * @return array
     */
    public function regenerateTOTPBackupCodes(): array{
        return mfa('totp')->regenerateBackupCodes($this);
    }

    /**
     * Get WebAuthn keys.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWebAuthnKeys(){
        return $this->webAuthnKeys()->get();
    }

    /**
     * Get WebAuthn keys count.
     *
     * @return int
     */
    public function getWebAuthnKeysCount(): int{
        return $this->webAuthnKeys()->count();
    }

    /**
     * Delete a WebAuthn key.
     *
     * @param int $keyId
     * @return bool
     */
    public function deleteWebAuthnKey(int $keyId): bool{
        return mfa('webauthn')->deleteKey($this, $keyId);
    }

    /**
     * Check if MFA is verified in current session.
     *
     * @return bool
     */
    public function isMFAVerified(): bool{
        return mfa()->isVerified();
    }

    /**
     * Get last MFA method used.
     *
     * @return MFAMethod|null
     */
    public function getLastUsedMFAMethod(): ?MFAMethod{
        return $this->mfaMethods()->where('is_enabled', true)->whereNotNull('last_used_at')->orderBy('last_used_at', 'desc')->first();
    }
}