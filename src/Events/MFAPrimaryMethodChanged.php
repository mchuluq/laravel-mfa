<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when primary MFA method is changed.
 */
class MFAPrimaryMethodChanged extends MFAEvent{
    /**
     * The old primary method.
     *
     * @var string|null
     */
    public $oldMethod;

    /**
     * The new primary method.
     *
     * @var string
     */
    public $newMethod;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $newMethod
     * @param string|null $oldMethod
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $newMethod, ?string $oldMethod = null, array $data = []){
        $this->oldMethod = $oldMethod;
        $this->newMethod = $newMethod;
        
        $data['old_method'] = $oldMethod;
        $data['new_method'] = $newMethod;
        
        parent::__construct($user, $newMethod, $data);
    }
}