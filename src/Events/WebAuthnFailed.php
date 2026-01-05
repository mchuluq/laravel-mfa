<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when WebAuthn verification fails.
 */
class WebAuthnFailed extends MFAEvent{
    /**
     * The reason for failure.
     *
     * @var string
     */
    public $reason;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $reason
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $reason = '', array $data = []){
        $this->reason = $reason;
        $data['reason'] = $reason;
        
        parent::__construct($user, 'webauthn', $data);
    }
}