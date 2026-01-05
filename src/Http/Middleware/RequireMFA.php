<?php namespace Mchuluq\LaravelMFA\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mchuluq\LaravelMFA\MFAManager;

class RequireMFA{
    /**
     * The MFA manager instance.
     *
     * @var MFAManager
     */
    protected $mfa;

    /**
     * Create a new middleware instance.
     *
     * @param MFAManager $mfa
     * @return void
     */
    public function __construct(MFAManager $mfa){
        $this->mfa = $mfa;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectRoute
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $redirectRoute = null){
        // Check if MFA is enabled globally
        if (!config('mfa.enabled', true)) {
            return $next($request);
        }
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        $user = Auth::user();
        // Check if user requires MFA
        if (!$this->mfa->requiresMFA($user)) {
            return $next($request);
        }
        // Store intended URL
        if (!$request->ajax() && !$request->expectsJson()) {
            session()->put('mfa_intended_url', $request->fullUrl());
        }
        // Get enabled drivers, If no methods enabled, allow access (should not happen, but safe fallback)
        $enabledDrivers = $this->mfa->getEnabledDrivers($user);
        if (empty($enabledDrivers)) {
            return $next($request);
        }
        // Determine challenge route
        $challengeRoute = $this->determineChallengeRoute($user, $enabledDrivers, $redirectRoute);
        // Redirect to challenge
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'MFA verification required.',
                'redirect_url' => $challengeRoute,
                'code' => 'OTP_REQUIRED',
            ], 403);
        }
        return redirect($challengeRoute)->with([
            'mfa_required' => true,
            'message' => 'Please verify your identity to continue.',
        ]);
    }

    protected function determineChallengeRoute($user, array $enabledDrivers, ?string $customRoute = null): string{
        // If custom route specified, use it
        if ($customRoute) {
            return route($customRoute);
        }
        // Get primary method
        $primaryMethod = $this->mfa->getPrimaryMethod($user);
        // If primary method exists, redirect directly to that challenge
        if ($primaryMethod) {
            return route('mfa.challenge.show', ['driver' => $primaryMethod->driver]);
        }
        // If only one method enabled, redirect to that
        if (count($enabledDrivers) === 1) {
            $driverName = array_key_first($enabledDrivers);
            return route('mfa.challenge.show', ['driver' => $driverName]);
        }
        // Multiple methods without primary, show selection
        return route(config('mfa.middleware.challenge_route', 'mfa.challenge'));
    }
}