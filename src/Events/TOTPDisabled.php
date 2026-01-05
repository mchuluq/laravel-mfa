<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when TOTP is disabled for a user.
 */
class TOTPDisabled extends MFAEvent{
    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, array $data = []){
        parent::__construct($user, 'totp', $data);
    }
}