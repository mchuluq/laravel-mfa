# Laravel MFA (Multi-Factor Authentication)

Multi-Factor Authentication package for Laravel 8+ with support for TOTP, Email OTP, and WebAuthn/Passkey.

## Features

- 🔐 **Multiple MFA Methods**: TOTP (Authenticator Apps), Email OTP, WebAuthn/Passkey
- 🎨 **Driver Pattern**: Easy to extend with custom drivers
- 🛡️ **Security First**: Rate limiting, backup codes, device remembering
- 🎯 **Middleware Based**: Simple integration as authentication layer
- 📱 **User Friendly**: Multiple methods per user, fallback options
- 🔧 **Highly Configurable**: Extensive configuration options
- 🎭 **Laravel 8+ Compatible**: Built specifically for Laravel 8

## Requirements

- PHP ^7.4 or ^8.0
- Laravel ^8.0
- MySQL/PostgreSQL/SQLite

## Installation

### 1. Install via Composer

```bash
composer require mchuluq/laravel-mfa
```

### 2. Publish 

```bash
php artisan vendor:publish --tag=mfa-config
php artisan vendor:publish --tag=mfa-migrations
php artisan vendor:publish --tag=mfa-vue
php artisan vendor:publish --tag=mfa-blade
php artisan migrate
```

### 3. Add Trait to User Model

```php
use Mchuluq\LaravelMFA\Traits\HasMFA;

class User extends Authenticatable
{
    use HasMFA;
    
    // ...
}
```

## Configuration

Edit `config/mfa.php`:

```php
return [
    'enabled' => true,
    
    'drivers' => [
        'totp' => [
            'enabled' => true,
            // ...
        ],
        'email_otp' => [
            'enabled' => true,
            // ...
        ],
        'webauthn' => [
            'enabled' => true,
            // ...
        ],
    ],
    
    // ...
];
```

## Usage

### Protect Routes with MFA

```php
// routes/web.php
Route::middleware(['auth', 'mfa'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});
```

### Setup MFA for User

```php
// In your controller
public function enableTotp(Request $request)
{
    $user = auth()->user();
    
    // Setup TOTP
    $setup = mfa('totp')->setup($user);
    
    return view('mfa.setup.totp', [
        'qrCode' => $setup['qr_code'],
        'secret' => $setup['secret'],
        'backupCodes' => $setup['backup_codes'],
    ]);
}

public function verifyTotp(Request $request)
{
    $user = auth()->user();
    $code = $request->input('code');
    
    if (mfa('totp')->verify($user, $code)) {
        return redirect()->route('dashboard')
            ->with('success', 'MFA enabled successfully!');
    }
    
    return back()->withErrors(['code' => 'Invalid code']);
}
```

### Check MFA Status

```php
// Check if user has MFA enabled
if ($user->hasMFAEnabled()) {
    // ...
}

// Get enabled methods
$methods = $user->getMFAMethods();

// Get primary method
$primary = $user->getPrimaryMFAMethod();
```

### Using Helper Functions

```php
// Get MFA manager
$manager = mfa();

// Get specific driver
$totp = mfa('totp');

// Check if MFA is verified in session
if (mfa_verified()) {
    // User has verified MFA
}

// Check if user requires MFA
if (mfa_required()) {
    // Redirect to challenge
}
```

## Available Drivers

### 1. TOTP (Time-based One-Time Password)

Works with authenticator apps like:
- Google Authenticator
- Microsoft Authenticator
- Authy
- 1Password

```php
// Setup
$setup = mfa('totp')->setup($user);

// Verify
$isValid = mfa('totp')->verify($user, $code);

// Disable
mfa('totp')->disable($user);
```

### 2. Email OTP

Send verification codes via email.

```php
// Send challenge
mfa('email_otp')->challenge($user);

// Verify
$isValid = mfa('email_otp')->verify($user, $code);
```

### 3. WebAuthn / Passkey

Hardware security keys and biometric authentication.

```php
// Setup
$options = mfa('webauthn')->setup($user);

// Verify
$isValid = mfa('webauthn')->verify($user, $credential);
```

## Security Features

- **Rate Limiting**: Configurable max attempts and lockout
- **Backup Codes**: Emergency access codes for TOTP
- **Remember Device**: Optional trusted device feature
- **Session Timeout**: Automatic MFA session expiration
- **Audit Logging**: Track all MFA events

## Events

Listen to MFA events:

```php
use Mchuluq\LaravelMFA\Events\MFAEnabled;
use Mchuluq\LaravelMFA\Events\MFAVerified;
use Mchuluq\LaravelMFA\Events\MFAFailed;

// In EventServiceProvider
protected $listen = [
    MFAEnabled::class => [
        SendMFAEnabledNotification::class,
    ],
    MFAVerified::class => [
        LogMFAVerification::class,
    ],
];
```


## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security issues, please email security@example.com instead of using the issue tracker.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Your Name](https://github.com/mchuluq)