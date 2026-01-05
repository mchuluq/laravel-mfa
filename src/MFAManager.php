<?php namespace Mchuluq\LaravelMFA;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use Mchuluq\LaravelMFA\Contracts\MFADriverContract;
use Mchuluq\LaravelMFA\Exceptions\MFAException;
use Mchuluq\LaravelMFA\Drivers\TOTPDriver;
use Mchuluq\LaravelMFA\Drivers\EMailOTPDriver;
use Mchuluq\LaravelMFA\Drivers\WebAuthnDriver;
use Mchuluq\LaravelMFA\Models\MFAMethod;

class MFAManager extends Manager{
    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver(){
        return config('mfa.ui.default_primary_method', 'totp');
    }

    /**
     * Create TOTP driver instance.
     *
     * @return TOTPDriver
     */
    protected function createTotpDriver(){
        return new TOTPDriver($this->container,config('mfa.drivers.totp', []));
    }

    /**
     * Create Email OTP driver instance.
     *
     * @return EMailOTPDriver
     */
    protected function createEmailOtpDriver(){
        return new EMailOTPDriver($this->container,config('mfa.drivers.email_otp', []));
    }

    /**
     * Create WebAuthn driver instance.
     *
     * @return WebAuthnDriver
     */
    protected function createWebauthnDriver(){
        return new WebAuthnDriver($this->container,config('mfa.drivers.webauthn', []));
    }

    /**
     * Get all available drivers.
     *
     * @return array
     */
    public function getAvailableDrivers(): array{
        $drivers = [];
        foreach (config('mfa.drivers', []) as $name => $config) {
            if (isset($config['enabled']) && $config['enabled']) {
                try {
                    $drivers[$name] = $this->driver($name);
                } catch (\Exception $e) {
                    // Skip drivers that fail to instantiate
                    continue;
                }
            }
        }
        return $drivers;
    }

    /**
     * Get enabled drivers for a user.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getEnabledDrivers(Authenticatable $user): array{
        $methods = MFAMethod::where('user_id', $user->getAuthIdentifier())->where('is_enabled', true)->get();
        $drivers = [];
        foreach ($methods as $method) {
            try {
                $driver = $this->driver($method->driver);
                if ($driver->isEnabled() && $driver->isConfigured($user)) {
                    $drivers[$method->driver] = [
                        'driver' => $driver,
                        'method' => $method,
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return $drivers;
    }

    /**
     * Get primary MFA method for user.
     *
     * @param Authenticatable $user
     * @return MFAMethod|null
     */
    public function getPrimaryMethod(Authenticatable $user): ?MFAMethod{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('is_primary', true)->where('is_enabled', true)->first();
    }

    /**
     * Set primary MFA method for user.
     *
     * @param Authenticatable $user
     * @param string $driverName
     * @return bool
     */
    public function setPrimaryMethod(Authenticatable $user, string $driverName): bool{
        // Remove primary flag from all methods
        MFAMethod::where('user_id', $user->getAuthIdentifier())->update(['is_primary' => false]);
        // Set new primary method
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', $driverName)->update(['is_primary' => true]) > 0;
    }

    /**
     * Check if user has MFA enabled.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function isEnabled(Authenticatable $user): bool{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('is_enabled', true)->exists();
    }

    /**
     * Check if user requires MFA verification.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function requiresMFA(Authenticatable $user): bool{
        if (!config('mfa.enabled', true)) {
            return false;
        }
        if (!$this->isEnabled($user)) {
            return false;
        }
        // Check if already verified in session
        if ($this->isVerified()) {
            return false;
        }
        // if via remember
        if(Auth::viaRemember() == true && config('mfa.auto_verified_from_remember', false) == true){
            $this->markAsVerified();
            return false;
        }
        return true;
    }

    /**
     * Check if MFA is verified in current session.
     *
     * @return bool
     */
    public function isVerified(): bool{
        $session_key = config('mfa.session_key', 'mfa_verified_at');
        $challenge_timeout = config('mfa.challenge_timeout', 0);
        $verified_at = session()->get($session_key, 0);
        if($challenge_timeout > 0 && (now()->timestamp - $verified_at) > $challenge_timeout){
            return false;
        }elseif($verified_at == 0){
            return false;
        }
        return true;
    }

    /**
     * Mark MFA as verified in session.
     *
     * @param string|null $driverName
     * @return void
     */
    public function markAsVerified(?string $driverName = null): void{
        $session_key = config('mfa.session_key', 'mfa_verified_at');
        session([
            $session_key => now()->timestamp,
            'mfa_driver_used' => $driverName,
        ]);
    }

    /**
     * Clear MFA verification from session.
     *
     * @return void
     */
    public function clearVerification(): void{
        $session_key = config('mfa.session_key', 'mfa_verified_at');
        session()->forget([
            $session_key,
            'mfa_driver_used'
        ]);
    }

    public function determineChallengeMethod($user): string|null{
        $primary_method = $this->getPrimaryMethod($user);
        $enabled_drivers = $this->getEnabledDrivers($user);
        if ($primary_method) {
            return $primary_method->driver;
        }
        if (count($enabled_drivers) === 1) {
            $driver_name = array_key_first($enabled_drivers);
            return $driver_name;
        }
        return null;
    }

    /**
     * Get MFA statistics for user.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getStatistics(Authenticatable $user): array{
        $methods = MFAMethod::where('user_id', $user->getAuthIdentifier())->get();
        $data = [
            'total_methods' => $methods->count(),
            'enabled_methods' => $methods->where('is_enabled', true)->count(),
            'primary_method' => $methods->where('is_primary', true)->first()?->driver,
            'last_used' => $methods->max('last_used_at'),
            'methods' => $methods->map(function ($method) {
                return [
                    'driver' => $method->driver,
                    'name' => $method->name,
                    'is_primary' => $method->is_primary,
                    'is_enabled' => $method->is_enabled,
                    'last_used_at' => $method->last_used_at,
                ];
            }),
        ];
        return $data;
    }

    /**
     * Update last used timestamp for a method.
     *
     * @param Authenticatable $user
     * @param string $driverName
     * @return void
     */
    public function updateLastUsed(Authenticatable $user, string $driverName): void{
        MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', $driverName)->update(['last_used_at' => now()]);
    }

    /**
     * Disable all MFA methods for user (emergency).
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function disableAll(Authenticatable $user): bool{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->update(['is_enabled' => false]) > 0;
    }

    /**
     * Enable a specific MFA method for user.
     *
     * @param Authenticatable $user
     * @param string $driverName
     * @return bool
     */
    public function enableMethod(Authenticatable $user, string $driverName): bool{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', $driverName)->update(['is_enabled' => true]) > 0;
    }
}