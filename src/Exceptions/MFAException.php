<?php namespace Mchuluq\LaravelMFA\Exceptions;

use Exception;

class MFAException extends Exception{
    /**
     * Create a new MFA exception for driver not found.
     *
     * @param string $driver
     * @return static
     */
    public static function driverNotFound(string $driver): self{
        return new static("MFA driver [{$driver}] not found.");
    }

    /**
     * Create a new MFA exception for driver not enabled.
     *
     * @param string $driver
     * @return static
     */
    public static function driverNotEnabled(string $driver): self{
        return new static("MFA driver [{$driver}] is not enabled.");
    }

    /**
     * Create a new MFA exception for method not configured.
     *
     * @param string $driver
     * @return static
     */
    public static function methodNotConfigured(string $driver): self{
        return new static("MFA method [{$driver}] is not configured for this user.");
    }

    /**
     * Create a new MFA exception for invalid verification code.
     *
     * @return static
     */
    public static function invalidCode(): self{
        return new static("The verification code is invalid or has expired.");
    }

    /**
     * Create a new MFA exception for rate limit exceeded.
     *
     * @param int $seconds
     * @return static
     */
    public static function rateLimitExceeded(int $seconds): self{
        $message = str_replace(
            ':seconds',
            $seconds,
            config('mfa.rate_limiting.lockout_message', 'Too many attempts. Please try again in :seconds seconds.')
        );
        return new static($message);
    }

    /**
     * Create a new MFA exception for challenge timeout.
     *
     * @return static
     */
    public static function challengeTimeout(): self{
        return new static("The MFA challenge has timed out. Please try again.");
    }

    /**
     * Create a new MFA exception for setup failure.
     *
     * @param string $reason
     * @return static
     */
    public static function setupFailed(string $reason = ''): self{
        $message = "Failed to setup MFA method.";        
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        return new static($message);
    }

    /**
     * Create a new MFA exception for verification failure.
     *
     * @param string $reason
     * @return static
     */
    public static function verificationFailed(string $reason = ''): self{
        $message = "MFA verification failed.";        
        if ($reason) {
            $message .= " Reason: {$reason}";
        }
        return new static($message);
    }

    /**
     * Create a new MFA exception for missing backup codes.
     *
     * @return static
     */
    public static function noBackupCodes(): self{
        return new static("No backup codes available. Please generate new backup codes.");
    }

    /**
     * Create a new MFA exception for invalid backup code.
     *
     * @return static
     */
    public static function invalidBackupCode(): self{
        return new static("The backup code is invalid or has already been used.");
    }

    /**
     * Create a new MFA exception for no methods enabled.
     *
     * @return static
     */
    public static function noMethodsEnabled(): self{
        return new static("No MFA methods are enabled for this user.");
    }

    /**
     * Create a new MFA exception for cannot disable last method.
     *
     * @return static
     */
    public static function cannotDisableLastMethod(): self{
        return new static("Cannot disable the last MFA method. Please add another method first.");
    }

    /**
     * Create a new MFA exception for WebAuthn error.
     *
     * @param string $message
     * @return static
     */
    public static function webAuthnError(string $message): self{
        return new static("WebAuthn error: {$message}");
    }
}