<?php namespace Mchuluq\LaravelMFA\Listeners;

use Illuminate\Support\Facades\Log;

/**
 * Example listener for logging MFA events.
 */
class LogMFAActivity{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event){
        if (config('mfa.security.log_events', true)) {
            Log::info('MFA Activity', $event->toArray());
        }
    }
}