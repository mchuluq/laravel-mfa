<?php namespace Mchuluq\LaravelMFA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mchuluq\LaravelMFA\MFAManager;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

class EMailOTPController extends Controller{
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
     * Show Email OTP management page.
     *
     * @return \Illuminate\View\View
     */
    public function index(){
        $user = Auth::user();
        $driver = $this->mfa->driver('email_otp');
        $isConfigured = $driver->isConfigured($user);
        $data = $driver->getData($user);
        $data = [
            'isConfigured' => $isConfigured,
            'data' => $data,
            'driver' => $driver
        ];
        if(request()->expectsJson() || $this->force_headless){
            return response()->json($data);  
        }else{
            return view('mfa::management.email-otp', $data);
        }
    }

    /**
     * Enable Email OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('email_otp');
        try {
            if ($driver->isConfigured($user)) {
                $message = 'Email OTP is already enabled.';
                if ($request->expectsJson() || $this->force_headless) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }
                return back()->with('info', $message);
            }
            $result = $driver->setup($user);
            $message = 'Email OTP has been enabled successfully.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $result,
                ]);
            }
            return redirect()->route('mfa.email-otp.index')->with('success', $message);
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
     * Disable Email OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('email_otp');
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
            $driver->disable($user);
            $message = 'Email OTP has been disabled.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }
            return redirect()->route('mfa.email-otp.index')->with('success', $message);
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
     * Send test OTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function sendTest(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('email_otp');
        try {
            if (!$driver->isConfigured($user)) {
                $message = 'Email OTP is not enabled.';
                if ($request->expectsJson() || $this->force_headless) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 422);
                }
                return back()->with('error', $message);
            }
            $result = $driver->challenge($user);
            $message = 'Test code has been sent to your email.';
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
}