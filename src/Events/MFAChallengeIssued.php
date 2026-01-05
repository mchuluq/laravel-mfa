<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when MFA challenge is issued.
 */
class MFAChallengeIssued extends MFAEvent{
    /**
     * Challenge expiration time.
     *
     * @var \Carbon\Carbon
     */
    public $expiresAt;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param \Carbon\Carbon $expiresAt
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, $expiresAt, array $data = []){
        $this->expiresAt = $expiresAt;
        $data['expires_at'] = $expiresAt->toIso8601String();
        
        parent::__construct($user, $driver, $data);
    }
}