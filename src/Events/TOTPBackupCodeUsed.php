<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when a backup code is used.
 */
class TOTPBackupCodeUsed extends MFAEvent{
    /**
     * Remaining backup codes count.
     *
     * @var int
     */
    public $remainingCodes;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param int $remainingCodes
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, int $remainingCodes, array $data = []){
        $this->remainingCodes = $remainingCodes;
        $data['remaining_codes'] = $remainingCodes;
        
        parent::__construct($user, 'totp', $data);
    }
}