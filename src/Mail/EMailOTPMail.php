<?php namespace Mchuluq\LaravelMFA\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Auth\Authenticatable;

class EMailOTPMail extends Mailable implements ShouldQueue{

    use Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var Authenticatable
     */
    public $user;

    /**
     * The OTP code.
     *
     * @var string
     */
    public $code;

    /**
     * Expiration time in seconds.
     *
     * @var int
     */
    public $expiresIn;

    /**
     * Create a new message instance.
     *
     * @param Authenticatable $user
     * @param string $code
     * @param int $expiresIn
     * @return void
     */
    public function __construct(Authenticatable $user, string $code, int $expiresIn){
        $this->user = $user;
        $this->code = $code;
        $this->expiresIn = $expiresIn;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(){
        $from = config('mfa.drivers.email_otp.mail.from', [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ]);

        $subject = config('mfa.drivers.email_otp.mail.subject', 'Your Verification Code');

        return $this->from($from['address'], $from['name'])
                    ->subject($subject)
                    ->markdown('mfa::emails.otp')
                    ->with([
                        'code' => $this->code,
                        'expiresInMinutes' => ceil($this->expiresIn / 60),
                        'userName' => $this->user->name ?? 'User',
                    ]);
    }
}