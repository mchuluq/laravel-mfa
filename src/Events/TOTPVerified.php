<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when TOTP is verified successfully.
 */
class TOTPVerified extends MFAEvent{
    /**
     * The verification method used (totp or backup_code).
     *
     * @var string
     */
    public $method;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $method
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $method = 'totp', array $data = []){
        $this->method = $method;
        $data['method'] = $method;
        
        parent::__construct($user, 'totp', $data);
    }
}