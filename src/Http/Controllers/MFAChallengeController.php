<?php namespace Mchuluq\LaravelMFA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Mchuluq\LaravelMFA\MFAManager;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

class MFAChallengeController extends Controller{
    /**
     * The MFA manager instance.
     *
     * @var MFAManager
     */
    protected $mfa;

    protected $force_headless = false;

    /**
     * Create a new controller instance.
     *
     * @param MFAManager $mfa
     * @return void
     */
    public function __construct(MFAManager $mfa){
        $this->mfa = $mfa;
        $this->middleware('auth');
    }

    /**
     * Show MFA challenge selection page.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request){
        $select_mode = $request->query('select');
        $user = Auth::user();
        // Check if already verified
        if ($this->mfa->isVerified()) {
            return $this->redirectToIntended();
        }
        // Get enabled drivers
        $drivers = $this->mfa->getEnabledDrivers($user);
        if (empty($drivers)) {
            return redirect()->route('home')->with('error', 'No MFA methods configured.');
        }
        // If only one method, redirect directly to that challenge
        if (count($drivers) === 1) {
            $driverName = array_key_first($drivers);
            return redirect()->route('mfa.challenge.show', ['driver' => $driverName]);
        }
        // Get primary method
        $primaryMethod = $this->mfa->getPrimaryMethod($user);
        
        // If primary method exists, redirect directly to that challenge
        if ($primaryMethod && $select_mode != 1) {
            return redirect()->route('mfa.challenge.show', ['driver' => $primaryMethod->driver]);
        }
        // If only one method, redirect directly to that challenge
        if (count($drivers) === 1) {
            $driverName = array_key_first($drivers);
            return redirect()->route('mfa.challenge.show', ['driver' => $driverName]);
        }
        // Multiple methods without primary, show selection
        return view('mfa::challenge.select', [
            'drivers' => $drivers,
            'primaryMethod' => $primaryMethod,
        ]);
    }

    /**
     * Show specific MFA challenge page.
     *
     * @param Request $request
     * @param string $driver
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(Request $request, string $driver){
        $user = Auth::user();
        // Check if already verified
        if ($this->mfa->isVerified()) {
            return $this->redirectToIntended();
        }
        try {
            $driverInstance = $this->mfa->driver($driver);
            // Check if driver is enabled and configured
            if (!$driverInstance->isEnabled() || !$driverInstance->isConfigured($user)) {
                return redirect()->route('mfa.challenge.index')->with('error', 'This MFA method is not available.');
            }
            // Issue challenge (for email OTP, this sends the email)
            $challengeData = $driverInstance->challenge($user);
            return view($driverInstance->getChallengeView(), [
                'driver' => $driver,
                'challengeData' => $challengeData,
                'driverName' => $driverInstance->getDisplayName(),
                'drivers' => $this->mfa->getEnabledDrivers($user),
            ]);
        } catch (MFAException $e) {
            return redirect()->route('mfa.challenge.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Verify MFA challenge.
     *
     * @param Request $request
     * @param string $driver
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request, string $driver){
        $user = Auth::user();
        try {
            $driverInstance = $this->mfa->driver($driver);
            // Validate input
            $validated = $driverInstance->validateVerification($request->all());
            // Verify credential
            $isValid = $driverInstance->verify($user, $validated['code'] ?? $validated['credential'] ?? null);
            if ($isValid) {
                // Mark as verified in session
                $this->mfa->markAsVerified($driver);
                // Remember device if requested
                $message = 'MFA verification successful.';
                if ($request->expectsJson() || $this->force_headless) {
                    return response()->json([
                        'success' => true,
                        'message' => $message,
                        'redirect' => $this->getIntendedUrl(),
                    ]);
                }
                return $this->redirectToIntended()->with('success', $message);
            }
            $message = 'Invalid verification code. Please try again.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }
            return back()->withErrors(['code' => $message,])->withInput();
        } catch (MFAException $e) {
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->withErrors(['code' => $e->getMessage(),])->withInput();
        } catch (\Exception $e) {
            $message = "An error occurred: ".$e->getMessage();
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 500);
            }
            return back()->withErrors(['code' => $message,])->withInput();
        }
    }

    /**
     * Resend challenge (for email OTP).
     *
     * @param Request $request
     * @param string $driver
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function resend(Request $request, string $driver){
        $user = Auth::user();
        try {
            $driverInstance = $this->mfa->driver($driver);
            // Only email OTP supports resend
            if (!method_exists($driverInstance, 'resend')) {
                abort(404);
            }
            $result = $driverInstance->resend($user);
            $message = 'Verification code has been resent.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ]);
            }
            return back()->with('success', $message);
        } catch (MFAException $e) {
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel MFA challenge and logout.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel(Request $request){
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $message = 'MFA verification cancelled.';
        if ($request->expectsJson() || $this->force_headless) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }
        return redirect()->route('logout')->with('message', $message);
    }

    /**
     * Redirect to intended URL.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToIntended(){
        $url = session()->pull('mfa_intended_url', config('mfa.authenticated_redirect_uri'));
        return redirect()->to($url);
    }

    /**
     * Get intended URL.
     *
     * @return string
     */
    protected function getIntendedUrl(): string{
        return session()->get('mfa_intended_url', config('mfa.authenticated_redirect_uri'));
    }
}