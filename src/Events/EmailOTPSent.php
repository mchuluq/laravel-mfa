<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when an Email OTP is sent to user.
 */
class EmailOTPSent extends MFAEvent{
    /**
     * The email address the OTP was sent to.
     *
     * @var string
     */
    public $email;

    /**
     * OTP expiration time in seconds.
     *
     * @var int
     */
    public $expiresIn;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $email
     * @param int $expiresIn
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $email = '', int $expiresIn = 600, array $data = []){
        $this->email = $email ?: ($user->email ?? '');
        $this->expiresIn = $expiresIn;
        
        $data['email'] = $this->email;
        $data['expires_in'] = $expiresIn;
        
        parent::__construct($user, 'email_otp', $data);
    }
}