<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Mail;
use Mchuluq\LaravelMFA\Models\EMailOTP;
use Mchuluq\LaravelMFA\Exceptions\MFAException;
use Mchuluq\LaravelMFA\Mail\EMailOTPMail;
use Mchuluq\LaravelMFA\Events\EMailOTPSent;
use Mchuluq\LaravelMFA\Events\EMailOTPVerified;

class EMailOTPDriver extends AbstractDriver{
    /**
     * The driver name.
     *
     * @var string
     */
    protected $name = 'email_otp';

    /**
     * Setup MFA for the user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function setup(Authenticatable $user, array $options = []){
        // Email OTP doesn't need setup, just enable it
        $this->enableMethod($user);
        $this->log('Email OTP enabled', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return [
            'message' => 'Email OTP has been enabled. You will receive codes via email.',
            'email' => $user->email,
        ];
    }

    /**
     * Verify the OTP code.
     *
     * @param Authenticatable $user
     * @param mixed $credential
     * @param array $options
     * @return bool
     */
    public function verify(Authenticatable $user, $credential, array $options = []): bool{
        $this->checkRateLimit($user);
        $code = (string) $credential;
        // Find the most recent valid OTP
        $otp = EmailOTP::where('user_id', $user->getAuthIdentifier())->where('code', $code)->valid()->latest('created_at')->first();
        if (!$otp) {
            $this->incrementRateLimit($user);
            $this->log('Email OTP verification failed - invalid code', [
                'user_id' => $user->getAuthIdentifier(),
                'code' => $code,
            ]);
            return false;
        }
        // Mark as verified
        $otp->markAsVerified();
        // Invalidate other OTPs
        $this->invalidateOtherOTPs($user, $otp->id);
        $this->clearRateLimit($user);
        $this->updateLastUsed($user);
        $this->fireEvent(EmailOTPVerified::class, [
            'user' => $user,
            'driver' => $this->name,
        ]);
        $this->log('Email OTP verification successful', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return true;
    }

    /**
     * Generate/Send challenge to user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return array
     */
    public function challenge(Authenticatable $user, array $options = []){
        // Check throttle
        $this->checkThrottle($user);
        // Generate OTP code
        $code = $this->generateCode();
        $expiresIn = $this->config['expires_in'] ?? 600; // seconds
        // Store OTP
        $otp = EMailOTP::create([
            'user_id' => $user->getAuthIdentifier(),
            'code' => $code,
            'expires_at' => now()->addSeconds($expiresIn),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        // Send email
        try {
            Mail::to($user->email)->send(new EmailOTPMail($user, $code, $expiresIn));
        } catch (\Exception $e) {
            $this->log('Failed to send email OTP', [
                'user_id' => $user->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw MFAException::setupFailed('Failed to send verification email.');
        }
        // Set throttle
        $this->setThrottle($user);
        $this->fireEvent(EmailOTPSent::class, [
            'user' => $user,
            'driver' => $this->name,
        ]);
        $this->log('Email OTP sent', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return [
            'message' => 'Verification code has been sent to your email.',
            'email' => $this->maskEmail($user->email),
            'expires_in' => $expiresIn,
            'otp_id' => $otp->id,
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
        // Delete all OTPs
        $deleted = EmailOTP::where('user_id', $user->getAuthIdentifier())->delete();
        $this->log('Email OTP disabled', [
            'user_id' => $user->getAuthIdentifier(),
        ]);
        return true;
    }

    /**
     * Get driver-specific data for the user.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getData(Authenticatable $user){
        $lastOTP = EmailOTP::where('user_id', $user->getAuthIdentifier())->latest('created_at')->first();
        return [
            'email' => $user->email,
            'last_sent_at' => $lastOTP?->created_at,
            'can_resend' => $this->canResend($user),
        ];
    }

    /**
     * Validate setup data.
     *
     * @param array $data
     * @return array
     */
    public function validateSetup(array $data): array{
        // No validation needed for setup
        return [];
    }

    /**
     * Validate verification data.
     *
     * @param array $data
     * @return array
     */
    public function validateVerification(array $data): array{
        return validator($data, [
            'code' => 'required|string|size:' . ($this->config['length'] ?? 6),
        ])->validate();
    }

    /**
     * Resend OTP code.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function resend(Authenticatable $user): array{
        if (!$this->canResend($user)) {
            $secondsRemaining = $this->getThrottleSecondsRemaining($user);
            throw MFAException::rateLimitExceeded($secondsRemaining);
        }
        return $this->challenge($user);
    }

    /**
     * Generate OTP code.
     *
     * @return string
     */
    protected function generateCode(): string{
        $length = $this->config['length'] ?? 6;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    /**
     * Invalidate other OTPs for the user.
     *
     * @param Authenticatable $user
     * @param int $exceptId
     * @return void
     */
    protected function invalidateOtherOTPs(Authenticatable $user, int $exceptId): void{
        EmailOTP::where('user_id', $user->getAuthIdentifier())->where('id', '!=', $exceptId)->whereNull('verified_at')->update(['verified_at' => now()]);
    }

    /**
     * Check if user can resend OTP.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function canResend(Authenticatable $user): bool{
        return !$this->isThrottled($user);
    }

    /**
     * Check throttle for sending OTP.
     *
     * @param Authenticatable $user
     * @return void
     * @throws MFAException
     */
    protected function checkThrottle(Authenticatable $user): void{
        if ($this->isThrottled($user)) {
            $secondsRemaining = $this->getThrottleSecondsRemaining($user);
            throw MFAException::rateLimitExceeded($secondsRemaining);
        }
    }

    /**
     * Check if user is throttled.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function isThrottled(Authenticatable $user): bool{
        $key = $this->getThrottleKey($user);
        return cache()->has($key);
    }

    /**
     * Set throttle for user.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function setThrottle(Authenticatable $user): void{
        $key = $this->getThrottleKey($user);
        $throttle = $this->config['throttle'] ?? 60;        
        cache()->put($key, true, now()->addSeconds($throttle));
    }

    /**
     * Get throttle cache key.
     *
     * @param Authenticatable $user
     * @return string
     */
    protected function getThrottleKey(Authenticatable $user): string{
        return sprintf('mfa_email_throttle:%s',$user->getAuthIdentifier());
    }

    /**
     * Get seconds remaining for throttle.
     *
     * @param Authenticatable $user
     * @return int
     */
    protected function getThrottleSecondsRemaining(Authenticatable $user): int{
        $key = $this->getThrottleKey($user);
        $expiresAt = (int) cache()->get($key . ':expires_at', 300);
        if (!$expiresAt) {
            // Fallback: estimate based on throttle config
            return $this->config['throttle'] ?? 60;
        }
        return max(0, $expiresAt - time());
    }

    /**
     * Mask email address.
     *
     * @param string $email
     * @return string
     */
    protected function maskEmail(string $email): string{
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }
        $username = $parts[0];
        $domain = $parts[1];
        $maskedUsername = substr($username, 0, 2) . str_repeat('*', max(0, strlen($username) - 2));
        return $maskedUsername . '@' . $domain;
    }

    /**
     * Clean up expired OTPs.
     *
     * @return int Number of deleted records
     */
    public function cleanupExpired(): int{
        return EmailOTP::expired()->delete();
    }
}