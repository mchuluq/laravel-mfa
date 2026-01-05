<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when Email OTP expires.
 */
class EmailOTPExpired extends MFAEvent{
    /**
     * The OTP ID that expired.
     *
     * @var int
     */
    public $otpId;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param int $otpId
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, int $otpId, array $data = []){
        $this->otpId = $otpId;
        $data['otp_id'] = $otpId;
        
        parent::__construct($user, 'email_otp', $data);
    }
}