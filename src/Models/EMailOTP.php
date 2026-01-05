<?php namespace Mchuluq\LaravelMFA\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EMailOTP extends Model{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'code',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []){
        parent::__construct($attributes);        
        $this->table = config('mfa.tables.email_otps', 'email_otps');
    }

    /**
     * Boot the model.
     *
     * @return void
     */
    protected static function boot(){
        parent::boot();
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    /**
     * Get the user that owns the email OTP.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo{
        return $this->belongsTo(
            config('auth.providers.users.model', \App\Models\User::class)
        );
    }

    /**
     * Scope a query to only include valid OTPs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query){
        return $query->where('expires_at', '>', now())->whereNull('verified_at');
    }

    /**
     * Scope a query to only include expired OTPs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query){
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to only include verified OTPs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query){
        return $query->whereNotNull('verified_at');
    }

    /**
     * Check if the OTP is expired.
     *
     * @return bool
     */
    public function isExpired(): bool{
        return $this->expires_at->isPast();
    }

    /**
     * Check if the OTP is verified.
     *
     * @return bool
     */
    public function isVerified(): bool{
        return $this->verified_at !== null;
    }

    /**
     * Check if the OTP is valid (not expired and not verified).
     *
     * @return bool
     */
    public function isValid(): bool{
        return !$this->isExpired() && !$this->isVerified();
    }

    /**
     * Mark the OTP as verified.
     *
     * @return bool
     */
    public function markAsVerified(): bool{
        return $this->update(['verified_at' => now()]);
    }

    /**
     * Get time remaining until expiration in seconds.
     *
     * @return int
     */
    public function getTimeRemainingAttribute(): int{
        if ($this->isExpired()) {
            return 0;
        }
        return now()->diffInSeconds($this->expires_at, false);
    }

    /**
     * Get formatted time remaining.
     *
     * @return string
     */
    public function getFormattedTimeRemainingAttribute(): string{
        $seconds = $this->time_remaining;
        if ($seconds <= 0) {
            return 'Expired';
        }
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}