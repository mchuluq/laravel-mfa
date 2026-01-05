<?php namespace Mchuluq\LaravelMFA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mchuluq\LaravelMFA\MFAManager;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

class MFAManagementController extends Controller{
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
     * Show MFA management dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index(){
        $user = Auth::user();
        // Get available drivers
        $availableDrivers = $this->mfa->getAvailableDrivers();
        // Get enabled methods for user
        $enabledDrivers = $this->mfa->getEnabledDrivers($user);
        // Get primary method
        $primaryMethod = $this->mfa->getPrimaryMethod($user);
        // Get statistics
        $statistics = $this->mfa->getStatistics($user,true);
        $data = [
            'availableDrivers' => $availableDrivers,
            'enabledDrivers' => $enabledDrivers,
            'primaryMethod' => $primaryMethod,
            'statistics' => $statistics,
            'hasMFAEnabled' => $user->hasMFAEnabled(),
        ];
        if(request()->expectsJson() || $this->force_headless){
            return response()->json($data);  
        }else{
            return view('mfa::management.index', $data);
        }
    }

    /**
     * Set primary MFA method.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function setPrimary(Request $request){
        $user = Auth::user();
        $validated = $request->validate([
            'driver' => 'required|string',
        ]);
        try {
            $driver = $this->mfa->driver($validated['driver']);
            if (!$driver->isConfigured($user)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This MFA method is not configured.',
                    ], 422);
                }
                return back()->with('error', 'This MFA method is not configured.');
            }
            $this->mfa->setPrimaryMethod($user, $validated['driver']);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Primary MFA method has been updated.',
                ]);
            }
            return back()->with('success', 'Primary MFA method has been updated.');
        } catch (MFAException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Enable a specific MFA method.
     *
     * @param Request $request
     * @param string $driver
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function enable(Request $request, string $driver){
        $user = Auth::user();
        try {
            $driverInstance = $this->mfa->driver($driver);
            if ($driverInstance->isConfigured($user)) {
                $this->mfa->enableMethod($user, $driver);
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'MFA method has been enabled.',
                    ]);
                }
                return back()->with('success', 'MFA method has been enabled.');
            }
            // Redirect to setup page
            $setupRoute = "mfa.{$driver}.create";            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Method not configured. Please complete setup first.',
                    'redirect' => route($setupRoute),
                ], 422);
            }
            return redirect()->route($setupRoute)
                ->with('info', 'Please complete the setup to enable this method.');
        } catch (MFAException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Disable a specific MFA method.
     *
     * @param Request $request
     * @param string $driver
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function disable(Request $request, string $driver){
        $user = Auth::user();
        try {
            // Require password confirmation if configured
            if (config('mfa.security.require_password_confirmation', true)) {
                $request->validate([
                    'password' => ['required',function($value, $attribute) use ($user){
                        if (!Hash::check($value, $user->password)) {
                            return false;
                        }
                        return true;
                    }],
                ]);
            }
            $driverInstance = $this->mfa->driver($driver);
            $driverInstance->disable($user);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'MFA method has been disabled.',
                ]);
            }
            return back()->with('success', 'MFA method has been disabled.');
        } catch (MFAException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Disable all MFA methods (emergency).
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function disableAll(Request $request){
        $user = Auth::user();
        // Require password confirmation
        $request->validate([
            'password' => ['required',function($value, $attribute) use ($user){
                if (!Hash::check($value, $user->password)) {
                    return false;
                }
                return true;
            }],
            'confirmation' => 'required|in:DISABLE',
        ]);
        try {
            $this->mfa->disableAll($user);
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'All MFA methods have been disabled.',
                ]);
            }
            return redirect()->route('mfa.management.index')
                ->with('warning', 'All MFA methods have been disabled. Your account security has been reduced.');
        } catch (MFAException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get MFA statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(){
        $user = Auth::user();
        $statistics = $this->mfa->getStatistics($user,true);
        return response()->json([
            'success' => true,
            'statistics' => $statistics,
            // 'sessions' => session()->all(),
            // 'via_remember' => Auth::viaRemember(),
        ]);
    }

}