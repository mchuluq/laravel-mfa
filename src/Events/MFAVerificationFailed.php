<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when MFA verification fails.
 */
class MFAVerificationFailed extends MFAEvent{
    /**
     * The reason for failure.
     *
     * @var string
     */
    public $reason;

    /**
     * Number of failed attempts.
     *
     * @var int
     */
    public $attempts;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param string $reason
     * @param int $attempts
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, string $reason = '', int $attempts = 0, array $data = []){
        $this->reason = $reason;
        $this->attempts = $attempts;
        
        $data['reason'] = $reason;
        $data['attempts'] = $attempts;
        
        parent::__construct($user, $driver, $data);
    }
}