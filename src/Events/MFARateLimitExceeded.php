<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when rate limit is exceeded.
 */
class MFARateLimitExceeded extends MFAEvent{
    /**
     * Number of attempts made.
     *
     * @var int
     */
    public $attempts;

    /**
     * Seconds until next attempt allowed.
     *
     * @var int
     */
    public $retryAfter;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param int $attempts
     * @param int $retryAfter
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, int $attempts, int $retryAfter, array $data = []){
        $this->attempts = $attempts;
        $this->retryAfter = $retryAfter;
        
        $data['attempts'] = $attempts;
        $data['retry_after'] = $retryAfter;
        
        parent::__construct($user, $driver, $data);
    }
}