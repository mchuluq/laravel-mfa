<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use OTPHP\TOTP;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Mchuluq\LaravelMFA\Models\TOTPSecret;
use Mchuluq\LaravelMFA\Exceptions\MFAException;
use Mchuluq\LaravelMFA\Events\TOTPEnabled;
use Mchuluq\LaravelMFA\Events\TOTPVerified;
use Mchuluq\LaravelMFA\Events\TOTPDisabled;

class TOTPDriver extends AbstractDriver{
    /**
     * The driver name.
     *
     * @var string
     */
    protected $name = 'totp';

    /**
     * Setup MFA for the user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function setup(Authenticatable $user, array $options = []){
        // Check if already configured
        if ($this->isConfigured($user)) {
            throw MFAException::setupFailed('TOTP is already configured for this user.');
        }
        // Generate TOTP secret
        $totp = $this->generateTOTP($user);
        $secret = $totp->getSecret();
        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        // Store in database (not verified yet)
        TOTPSecret::updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'secret' => $secret,
                'backup_codes' => $backupCodes,
                'verified_at' => null,
            ]
        );
        // Generate QR code
        $qrCode = $this->generateQRCode($totp);
        $this->log('TOTP setup initiated', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return [
            'secret' => $secret,
            'qr_code' => $qrCode,
            'backup_codes' => $backupCodes,
            'provisioning_uri' => $totp->getProvisioningUri(),
        ];
    }

    /**
     * Verify the TOTP code.
     *
     * @param Authenticatable $user
     * @param mixed $credential
     * @param array $options
     * @return bool
     */
    public function verify(Authenticatable $user, $credential, array $options = []): bool{
        $this->checkRateLimit($user);
        $totpSecret = TOTPSecret::where('user_id', $user->getAuthIdentifier())->first();
        if (!$totpSecret) {
            $this->incrementRateLimit($user);
            throw MFAException::methodNotConfigured($this->name);
        }
        $code = (string) $credential;
        // Check if it's a backup code
        if (strlen($code) > 6 && $totpSecret->hasBackupCode($code)) {
            if ($totpSecret->useBackupCode($code)) {
                $this->handleSuccessfulVerification($user, $totpSecret, 'backup_code');
                return true;
            }
        }
        // Verify TOTP code
        $totp = $this->createTOTPFromSecret($totpSecret->decrypted_secret, $user);
        $window = $this->config['window'] ?? 1;
        $isValid = $totp->verify($code, null, $window);
        if ($isValid) {
            $this->handleSuccessfulVerification($user, $totpSecret, 'totp');
            return true;
        }
        $this->incrementRateLimit($user);
        $this->log('TOTP verification failed', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return false;
    }

    /**
     * Handle successful verification.
     *
     * @param Authenticatable $user
     * @param TOTPSecret $totpSecret
     * @param string $method
     * @return void
     */
    protected function handleSuccessfulVerification(
        Authenticatable $user, 
        TOTPSecret $totpSecret, 
        string $method
    ): void {
        // Mark as verified if first time
        if (!$totpSecret->isVerified()) {
            $totpSecret->markAsVerified();
            $this->enableMethod($user);
            $this->fireEvent(TOTPEnabled::class, [
                'user' => $user,
                'driver' => $this->name,
            ]);
        }
        $this->clearRateLimit($user);
        $this->updateLastUsed($user);
        $this->fireEvent(TOTPVerified::class, [
            'user' => $user,
            'driver' => $this->name,
            'method' => $method,
        ]);
        $this->log('TOTP verification successful', [
            'user_id' => $user->getAuthIdentifier(),
            'method' => $method,
        ]);
    }

    /**
     * Generate/Send challenge to user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return mixed
     */
    public function challenge(Authenticatable $user, array $options = []){
        // TOTP doesn't need to send anything, user checks their app
        return [
            'message' => 'Please enter the code from your authenticator app.',
        ];
    }

    /**
     * Disable MFA for the user.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function disable(Authenticatable $user): bool{
        parent::disable($user);
        // Delete TOTP secret
        $deleted = TOTPSecret::where('user_id', $user->getAuthIdentifier())->delete();
        if ($deleted) {
            $this->fireEvent(TOTPDisabled::class, [
                'user' => $user,
                'driver' => $this->name,
            ]);
            $this->log('TOTP disabled', [
                'user_id' => $user->getAuthIdentifier(),
            ]);
        }
        return $deleted > 0;
    }

    /**
     * Get driver-specific data for the user.
     *
     * @param Authenticatable $user
     * @return mixed
     */
    public function getData(Authenticatable $user){
        $totpSecret = TOTPSecret::where('user_id', $user->getAuthIdentifier())->first();
        if (!$totpSecret) {
            return null;
        }
        return [
            'verified_at' => $totpSecret->verified_at,
            'remaining_backup_codes' => $totpSecret->remaining_backup_codes,
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
            'code' => 'required|string|size:6|regex:/^[0-9]+$/',
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
            'code' => 'required|string|min:6|max:16',
        ])->validate();
    }

    /**
     * Get recovery options for this driver.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getRecoveryOptions(Authenticatable $user): array{
        $totpSecret = TOTPSecret::where('user_id', $user->getAuthIdentifier())->first();
        if (!$totpSecret || $totpSecret->remaining_backup_codes === 0) {
            return [];
        }
        return [
            'backup_codes' => [
                'available' => true,
                'remaining' => $totpSecret->remaining_backup_codes,
                'description' => 'Use one of your backup codes to verify.',
            ],
        ];
    }

    /**
     * Regenerate backup codes.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function regenerateBackupCodes(Authenticatable $user): array{
        $totpSecret = TOTPSecret::where('user_id', $user->getAuthIdentifier())->first();
        if (!$totpSecret) {
            throw MFAException::methodNotConfigured($this->name);
        }
        $backupCodes = $this->generateBackupCodes();
        $totpSecret->update(['backup_codes' => $backupCodes]);
        $this->log('Backup codes regenerated', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return $backupCodes;
    }

    /**
     * Generate TOTP instance.
     *
     * @param Authenticatable $user
     * @return TOTP
     */
    protected function generateTOTP(Authenticatable $user): TOTP{
        $totp = TOTP::create(null, 
            $this->config['period'] ?? 30,
            $this->config['algorithm'] ?? 'sha1',
            $this->config['digits'] ?? 6
        );
        $issuer = $this->config['issuer'] ?? config('app.name');
        $label = $user->email ?? $user->getAuthIdentifier();
        $totp->setLabel($label);
        $totp->setIssuer($issuer);
        return $totp;
    }

    /**
     * Create TOTP from existing secret.
     *
     * @param string $secret
     * @param Authenticatable $user
     * @return TOTP
     */
    protected function createTOTPFromSecret(string $secret, Authenticatable $user): TOTP{
        $totp = TOTP::create($secret,
            $this->config['period'] ?? 30,
            $this->config['algorithm'] ?? 'sha1',
            $this->config['digits'] ?? 6
        );
        $issuer = $this->config['issuer'] ?? config('app.name');
        $label = $user->email ?? $user->getAuthIdentifier();
        $totp->setLabel($label);
        $totp->setIssuer($issuer);
        return $totp;
    }

    /**
     * Generate QR code.
     *
     * @param TOTP $totp
     * @return string
     */
    protected function generateQRCode(TOTP $totp): string{
        $size = $this->config['qr_code_size'] ?? 300;
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($totp->getProvisioningUri());
    }

    /**
     * Generate backup codes.
     *
     * @return array
     */
    protected function generateBackupCodes(): array{
        $count = $this->config['backup_codes']['count'] ?? 8;
        $length = $this->config['backup_codes']['length'] ?? 10;
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateBackupCode($length);
        }
        return $codes;
    }

    /**
     * Generate a single backup code.
     *
     * @param int $length
     * @return string
     */
    protected function generateBackupCode(int $length = 10): string{
        $characters = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
            
            // Add hyphen every 4 characters for readability
            if ($i > 0 && ($i + 1) % 4 === 0 && $i !== $length - 1) {
                $code .= '-';
            }
        }
        return $code;
    }
}