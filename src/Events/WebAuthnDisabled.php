<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when WebAuthn is disabled for a user.
 */
class WebAuthnDisabled extends MFAEvent{
    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, array $data = []){
        parent::__construct($user, 'webauthn', $data);
    }
}