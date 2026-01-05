<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when Email OTP is disabled for a user.
 */
class EmailOTPDisabled extends MFAEvent{
    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, array $data = []){
        parent::__construct($user, 'email_otp', $data);
    }
}