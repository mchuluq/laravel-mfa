<?php namespace Mchuluq\LaravelMFA\Listeners;

use Mchuluq\LaravelMFA\Events\TOTPBackupCodeUsed;

/**
 * Example listener for warning about low backup codes.
 */
class WarnLowBackupCodes{
    /**
     * Handle the event.
     *
     * @param  TOTPBackupCodeUsed  $event
     * @return void
     */
    public function handle(TOTPBackupCodeUsed $event){
        // Warn user when backup codes are running low
        if ($event->remainingCodes <= 2) {
            // Notification::send($event->user, new LowBackupCodesNotification($event));
        }
    }
}