<?php namespace Mchuluq\LaravelMFA\Listeners;

use Mchuluq\LaravelMFA\Events\MFAVerified;


/**
 * Example listener for sending notifications on MFA verification.
 */
class NotifyMFAVerification{
    /**
     * Handle the event.
     *
     * @param  MFAVerified  $event
     * @return void
     */
    public function handle(MFAVerified $event){
        // Send notification to user about successful MFA verification
        // Notification::send($event->user, new MFAVerifiedNotification($event));
    }
}