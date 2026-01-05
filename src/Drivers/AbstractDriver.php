<?php namespace Mchuluq\LaravelMFA\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Mchuluq\LaravelMFA\Contracts\MFADriverContract;
use Mchuluq\LaravelMFA\Models\MFAMethod;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

abstract class AbstractDriver implements MFADriverContract{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * The driver configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * The driver name.
     *
     * @var string
     */
    protected $name;

    /**
     * Create a new driver instance.
     *
     * @param Application $app
     * @param array $config
     */
    public function __construct(Application $app, array $config = []){
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string{
        return $this->name;
    }

    /**
     * Get the driver display name.
     *
     * @return string
     */
    public function getDisplayName(): string{
        return $this->config['name'] ?? ucfirst($this->name);
    }

    /**
     * Get the driver description.
     *
     * @return string
     */
    public function getDescription(): string{
        return $this->config['description'] ?? '';
    }

    /**
     * Check if the driver is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool{
        return $this->config['enabled'] ?? false;
    }

    /**
     * Check if user has this MFA method configured.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function isConfigured(Authenticatable $user): bool{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', $this->name)->where('is_enabled', true)->exists();
    }

    /**
     * Get or create MFA method record for user.
     *
     * @param Authenticatable $user
     * @return MFAMethod
     */
    protected function getOrCreateMethod(Authenticatable $user): MFAMethod{
        return MFAMethod::firstOrCreate(
            [
                'user_id' => $user->getAuthIdentifier(),
                'driver' => $this->name,
            ],
            [
                'name' => $this->getDisplayName(),
                'is_primary' => false,
                'is_enabled' => false,
            ]
        );
    }

    /**
     * Get MFA method for user.
     *
     * @param Authenticatable $user
     * @return MFAMethod|null
     */
    protected function getMethod(Authenticatable $user): ?MFAMethod{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', $this->name)->first();
    }

    /**
     * Enable the MFA method for user.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function enableMethod(Authenticatable $user): bool{
        $method = $this->getOrCreateMethod($user);
        // If this is the first method, make it primary
        $hasOtherMethods = MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', '!=', $this->name)->where('is_enabled', true)->exists();
        return $method->update([
            'is_enabled' => true,
            'is_primary' => !$hasOtherMethods,
        ]);
    }

    /**
     * Disable MFA for the user.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function disable(Authenticatable $user): bool{
        $method = $this->getMethod($user);
        if (!$method) {
            return false;
        }
        // Check if this is the only method
        if ($this->isOnlyMethod($user)) {
            throw MFAException::cannotDisableLastMethod();
        }
        // If this was primary, make another method primary
        if ($method->is_primary) {
            $this->promoteAnotherMethodToPrimary($user);
        }
        return $method->update(['is_enabled' => false]);
    }

    /**
     * Check if this is the only enabled method.
     *
     * @param Authenticatable $user
     * @return bool
     */
    protected function isOnlyMethod(Authenticatable $user): bool{
        return MFAMethod::where('user_id', $user->getAuthIdentifier())->where('is_enabled', true)->count() === 1;
    }

    /**
     * Promote another method to primary.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function promoteAnotherMethodToPrimary(Authenticatable $user): void{
        $anotherMethod = MFAMethod::where('user_id', $user->getAuthIdentifier())->where('driver', '!=', $this->name)->where('is_enabled', true)->first();
        if ($anotherMethod) {
            $anotherMethod->update(['is_primary' => true]);
        }
    }

    /**
     * Update last used timestamp.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function updateLastUsed(Authenticatable $user): void{
        $method = $this->getMethod($user);
        if ($method) {
            $method->update(['last_used_at' => now()]);
        }
    }

    /**
     * Check rate limiting for verification attempts.
     *
     * @param Authenticatable $user
     * @return void
     * @throws MFAException
     */
    protected function checkRateLimit(Authenticatable $user): void{
        if (!config('mfa.rate_limiting.enabled', true)) {
            return;
        }
        $key = $this->getRateLimitKey($user);
        $maxAttempts = config('mfa.rate_limiting.max_attempts', 5);
        $decayMinutes = config('mfa.rate_limiting.decay_minutes', 15);
        $attempts = cache()->get($key, 0);
        if ($attempts >= $maxAttempts) {
            $availableAt = (int) cache()->get($key . ':timer');
            $seconds = $availableAt ? max(0, $availableAt - time()) : $decayMinutes * 60;
            throw MFAException::rateLimitExceeded($seconds);
        }
    }

    /**
     * Increment rate limit counter.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function incrementRateLimit(Authenticatable $user): void{
        if (!config('mfa.rate_limiting.enabled', true)) {
            return;
        }
        $key = $this->getRateLimitKey($user);
        $decayMinutes = config('mfa.rate_limiting.decay_minutes', 15);
        $attempts = (int) cache()->get($key, 0) + 1;
        $expiresAt = now()->addMinutes($decayMinutes);
        cache()->put($key, $attempts, $expiresAt);
        cache()->put($key . ':timer', $expiresAt->timestamp, $expiresAt);
    }

    /**
     * Clear rate limit counter.
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function clearRateLimit(Authenticatable $user): void{
        $key = $this->getRateLimitKey($user);
        cache()->forget($key . ':timer');
    }

    /**
     * Get rate limit cache key.
     *
     * @param Authenticatable $user
     * @return string
     */
    protected function getRateLimitKey(Authenticatable $user): string{
        return sprintf(
            'mfa_rate_limit:%s:%s:%s',
            $this->name,
            $user->getAuthIdentifier(),
            request()->ip()
        );
    }

    /**
     * Get the challenge view name.
     *
     * @return string
     */
    public function getChallengeView(): string{
        return "mfa::challenge.{$this->name}";
    }

    /**
     * Get the setup view name.
     *
     * @return string
     */
    public function getSetupView(): string{
        return "mfa::setup.{$this->name}";
    }

    /**
     * Get the management view name.
     *
     * @return string
     */
    public function getManagementView(): string{
        return "mfa::management.{$this->name}";
    }

    /**
     * Get recovery options for this driver.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getRecoveryOptions(Authenticatable $user): array{
        return [];
    }

    /**
     * Handle recovery process.
     *
     * @param Authenticatable $user
     * @param string $method
     * @param mixed $credential
     * @return bool
     */
    public function recover(Authenticatable $user, string $method, $credential): bool{
        return false;
    }

    /**
     * Fire an event.
     *
     * @param string $event
     * @param array $payload
     * @return void
     */
    protected function fireEvent(string $event, array $payload = []): void{
        if (config('mfa.events.enabled', true)) {
            event($event, $payload);
        }
    }

    /**
     * Log an event if logging is enabled.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $message, array $context = []): void{
        if (config('mfa.security.log_events', true)) {
            logger()->info("[MFA - {$this->name}] {$message}", $context);
        }
    }
}