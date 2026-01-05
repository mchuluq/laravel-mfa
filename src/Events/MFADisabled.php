<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when any MFA method is disabled.
 */
class MFADisabled extends MFAEvent{
    /**
     * Whether this was the last MFA method.
     *
     * @var bool
     */
    public $wasLastMethod;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param bool $wasLastMethod
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, bool $wasLastMethod = false, array $data = []){
        $this->wasLastMethod = $wasLastMethod;
        $data['was_last_method'] = $wasLastMethod;
        
        parent::__construct($user, $driver, $data);
    }
}