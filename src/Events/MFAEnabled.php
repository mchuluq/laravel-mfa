<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when any MFA method is enabled.
 */
class MFAEnabled extends MFAEvent{
    /**
     * Whether this is the first MFA method.
     *
     * @var bool
     */
    public $isFirstMethod;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param bool $isFirstMethod
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, bool $isFirstMethod = false, array $data = []){
        $this->isFirstMethod = $isFirstMethod;
        $data['is_first_method'] = $isFirstMethod;
        
        parent::__construct($user, $driver, $data);
    }
}