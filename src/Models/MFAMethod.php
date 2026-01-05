<?php namespace Mchuluq\LaravelMFA\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MFAMethod extends Model{
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
        'driver',
        'name',
        'is_primary',
        'is_enabled',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'is_enabled' => 'boolean',
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
        $this->table = config('mfa.tables.methods', 'mfa_methods');
    }

    /**
     * Get the user that owns the MFA method.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo{
        return $this->belongsTo(
            config('auth.providers.users.model', \App\Models\User::class)
        );
    }

    /**
     * Scope a query to only include enabled methods.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query){
        return $query->where('is_enabled', true);
    }

    /**
     * Scope a query to only include primary method.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePrimary($query){
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to filter by driver.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $driver
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDriver($query, string $driver){
        return $query->where('driver', $driver);
    }

    /**
     * Scope a query to filter by user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId){
        return $query->where('user_id', $userId);
    }

    /**
     * Get the display name for the driver.
     *
     * @return string
     */
    public function getDriverDisplayNameAttribute(): string{
        return config("mfa.drivers.{$this->driver}.name", ucfirst($this->driver));
    }

    /**
     * Get the description for the driver.
     *
     * @return string
     */
    public function getDriverDescriptionAttribute(): string{
        return config("mfa.drivers.{$this->driver}.description", '');
    }

    /**
     * Mark this method as used.
     *
     * @return bool
     */
    public function markAsUsed(): bool{
        return $this->update(['last_used_at' => now()]);
    }

    /**
     * Set this method as primary.
     *
     * @return bool
     */
    public function setAsPrimary(): bool{
        // Remove primary flag from other methods
        static::where('user_id', $this->user_id)->where('id', '!=', $this->id)->update(['is_primary' => false]);
        return $this->update(['is_primary' => true]);
    }

    /**
     * Enable this method.
     *
     * @return bool
     */
    public function enable(): bool{
        return $this->update(['is_enabled' => true]);
    }

    /**
     * Disable this method.
     *
     * @return bool
     */
    public function disable(): bool{
        return $this->update(['is_enabled' => false]);
    }
}