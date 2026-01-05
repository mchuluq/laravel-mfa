<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when all MFA methods are disabled (emergency).
 */
class MFAEmergencyDisabled extends MFAEvent{
    /**
     * The admin user who disabled MFA.
     *
     * @var Authenticatable|null
     */
    public $disabledBy;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param Authenticatable|null $disabledBy
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, ?Authenticatable $disabledBy = null, array $data = []){
        $this->disabledBy = $disabledBy;
        
        if ($disabledBy) {
            $data['disabled_by'] = $disabledBy->getAuthIdentifier();
        }
        
        parent::__construct($user, 'all', $data);
    }
}