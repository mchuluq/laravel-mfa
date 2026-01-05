<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when MFA verification is successful.
 */
class MFAVerified extends MFAEvent{
    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, array $data = []){
        parent::__construct($user, $driver, $data);
    }
}