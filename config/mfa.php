<?php

return [

    'enabled' => env('MFA_ENABLED', true),

    'tables' => [
        'methods' => 'mfa_methods',
        'totp_secrets' => 'mfa_totp_secrets',
        'email_otps' => 'mfa_email_otps',
        'webauthn_keys' => 'mfa_webauthn_keys',
    ],

    'challenge_timeout' => 900, // bisa diset 0 jika tidak ingin timeout
    'session_key' => 'mfa_verified_at',
    'auto_verified_from_remember' => true, // tidak perlu verifikasi lagi jika menggunakan remember me

    'drivers' => [
        'totp' => [
            'enabled' => true,
            'name' => 'Authenticator App',
            'description' => 'Use an authenticator app like Google Authenticator or Authy',
            'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
            'qr_code_size' => 300,
            'algorithm' => 'sha1',
            'digits' => 6,
            'period' => 30,
            'window' => 1, // Accept codes from +/- 1 period (30 seconds)
            'backup_codes' => [
                'enabled' => true,
                'count' => 8,
                'length' => 10,
            ],
        ],
        'email_otp' => [
            'enabled' => true,
            'name' => 'Email Code',
            'description' => 'Receive a verification code via email',
            'length' => 6,
            'expires_in' => 600, // seconds (10 minutes)
            'throttle' => 60, // seconds between sends
            'mail' => [
                'from' => [
                    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                    'name' => env('MAIL_FROM_NAME', 'Example'),
                ],
                'subject' => 'Your Verification Code',
            ],
        ],
        'webauthn' => [
            'enabled' => true,
            'name' => 'Security Key',
            'description' => 'Use a hardware security key or biometric authentication',
            'timeout' => 60000, // milliseconds
            'challenge_length' => 32,
            'user_verification' => 'preferred', // required, preferred, discouraged
            'attestation' => 'none', // none, indirect, direct
            'authenticator_attachment' => null, // platform, cross-platform, null for both
            'require_resident_key' => false,
        ],
    ],

    'middleware' => [
        'challenge_route' => 'mfa.challenge.index',
    ],

    'routes' => [
        'enabled' => true,
        'prefix' => 'mfa',
        'middleware' => ['web', 'auth'],
        'name_prefix' => 'mfa.',
    ],

    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 5,
        'decay_minutes' => 15,
        'lockout_message' => 'Too many verification attempts. Please try again in :seconds seconds.',
    ],

    'recovery' => [
        'backup_codes_enabled' => true,
        'recovery_email_enabled' => true,
    ],

    'security' => [
        'require_password_confirmation' => true,
        'log_events' => true,
    ],

    'ui' => [
        'show_recovery_codes_on_setup' => true,
        'allow_multiple_methods' => true,
        'default_primary_method' => 'totp',
    ],

    'events' => [
        'enabled' => true,
        'fire' => [
            'setup' => true,
            'verified' => true,
            'failed' => true,
            'disabled' => true,
        ],
    ],
];