<?php namespace Mchuluq\LaravelMFA\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebAuthnKey extends Model{
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
        'name',
        'credential_id',
        'public_key',
        'aaguid',
        'counter',
        'transports',
        'attestation_format',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'transports' => 'array',
        'counter' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []){
        parent::__construct($attributes);        
        $this->table = config('mfa.tables.webauthn_keys', 'webauthn_keys');
    }

    /**
     * Get the user that owns the WebAuthn key.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo{
        return $this->belongsTo(
            config('auth.providers.users.model', \App\Models\User::class)
        );
    }

    /**
     * Scope a query to only include keys for a specific user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId){
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to find by credential ID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $credentialId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByCredentialId($query, string $credentialId){
        return $query->where('credential_id', $credentialId);
    }

    /**
     * Increment the counter.
     *
     * @return bool
     */
    public function incrementCounter(): bool{
        return $this->increment('counter');
    }

    /**
     * Mark as used.
     *
     * @return bool
     */
    public function markAsUsed(): bool{
        return $this->update(['last_used_at' => now()]);
    }

    /**
     * Get the authenticator type based on AAGUID.
     *
     * @return string
     */
    public function getAuthenticatorTypeAttribute(): string{
        // Map AAGUIDs to known authenticator types
        // This is a simplified version, you can expand this
        $knownAuthenticators = [
            '00000000-0000-0000-0000-000000000000' => 'Unknown',
            // Add more AAGUIDs as needed
        ];

        return $knownAuthenticators[$this->aaguid] ?? 'Security Key';
    }

    /**
     * Get the display name for the key.
     *
     * @return string
     */
    public function getDisplayNameAttribute(): string{
        return $this->name ?: 'Security Key';
    }

    /**
     * Get the transport methods as a comma-separated string.
     *
     * @return string
     */
    public function getTransportsStringAttribute(): string{
        if (empty($this->transports)) {
            return 'N/A';
        }
        return implode(', ', array_map('ucfirst', $this->transports));
    }
}