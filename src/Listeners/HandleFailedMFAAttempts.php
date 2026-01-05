<?php namespace Mchuluq\LaravelMFA\Listeners;

use Mchuluq\LaravelMFA\Events\MFAVerificationFailed;

/**
 * Example listener for handling failed MFA attempts.
 */
class HandleFailedMFAAttempts{
    /**
     * Handle the event.
     *
     * @param  MFAVerificationFailed  $event
     * @return void
     */
    public function handle(MFAVerificationFailed $event){
        // Alert user about failed MFA attempt
        if ($event->attempts >= 3) {
            // Send security alert
            // Notification::send($event->user, new FailedMFAAttemptNotification($event));
        }
    }
}