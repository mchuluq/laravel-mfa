<?php namespace Mchuluq\LaravelMFA\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TOTPSecret extends Model{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'secret',
        'backup_codes',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'backup_codes' => 'array',
        'verified_at' => 'datetime',
    ];

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []){
        parent::__construct($attributes);        
        $this->table = config('mfa.tables.totp_secrets', 'totp_secrets');
    }

    /**
     * Get the user that owns the TOTP secret.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo{
        return $this->belongsTo(
            config('auth.providers.users.model', \App\Models\User::class)
        );
    }

    /**
     * Get the decrypted secret.
     *
     * @return string
     */
    public function getDecryptedSecretAttribute(): string{
        return Crypt::decryptString($this->secret);
    }

    /**
     * Set the encrypted secret.
     *
     * @param string $value
     * @return void
     */
    public function setSecretAttribute(string $value): void{
        $this->attributes['secret'] = Crypt::encryptString($value);
    }

    /**
     * Get the decrypted backup codes.
     *
     * @return array
     */
    public function getDecryptedBackupCodesAttribute(): array{
        if (!$this->backup_codes) {
            return [];
        }
        return array_map(function ($code) {
            return Crypt::decryptString($code);
        }, $this->backup_codes);
    }

    /**
     * Set the encrypted backup codes.
     *
     * @param array $value
     * @return void
     */
    public function setBackupCodesAttribute(array $value): void{
        $this->attributes['backup_codes'] = json_encode(array_map(function ($code) {
            return Crypt::encryptString($code);
        }, $value));
    }

    /**
     * Check if a backup code is valid.
     *
     * @param string $code
     * @return bool
     */
    public function hasBackupCode(string $code): bool{
        $codes = $this->decrypted_backup_codes;
        return in_array($code, $codes, true);
    }

    /**
     * Use a backup code (remove it from the list).
     *
     * @param string $code
     * @return bool
     */
    public function useBackupCode(string $code): bool{
        $codes = $this->decrypted_backup_codes;
        $key = array_search($code, $codes, true);
        if ($key === false) {
            return false;
        }
        unset($codes[$key]);
        $this->backup_codes = array_values($codes);
        $this->save();
        return true;
    }

    /**
     * Check if TOTP is verified.
     *
     * @return bool
     */
    public function isVerified(): bool{
        return $this->verified_at !== null;
    }

    /**
     * Mark as verified.
     *
     * @return bool
     */
    public function markAsVerified(): bool{
        return $this->update(['verified_at' => now()]);
    }

    /**
     * Get remaining backup codes count.
     *
     * @return int
     */
    public function getRemainingBackupCodesAttribute(): int{
        return count($this->decrypted_backup_codes);
    }
}