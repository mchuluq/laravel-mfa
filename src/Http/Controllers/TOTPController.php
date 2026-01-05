<?php namespace Mchuluq\LaravelMFA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mchuluq\LaravelMFA\MFAManager;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

class TOTPController extends Controller{
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
     * Show TOTP management page.
     *
     * @return \Illuminate\View\View
     */
    public function index(){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
        $isConfigured = $driver->isConfigured($user);
        $data = $driver->getData($user);
        $data = [
            'isConfigured' => $isConfigured,
            'data' => $data,
            'driver' => $driver,
        ];
        if(request()->expectsJson() || $this->force_headless){
            return response()->json($data);  
        }else{
            return view('mfa::management.totp', $data);
        }
    }

    /**
     * Show TOTP setup page.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create(){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
        if ($driver->isConfigured($user)) {
            $message = 'TOTP is already configured.';
            if (request()->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }
            return redirect()->route('mfa.totp.index')->with('info', $message);
        }
        try {
            $setup = $driver->setup($user);
            $data = [
                'secret' => $setup['secret'],
                'qrCode' => $setup['qr_code'],
                'backupCodes' => $setup['backup_codes'],
                'provisioningUri' => $setup['provisioning_uri'],
            ];
            if (request()->expectsJson() || $this->force_headless) {
                return response()->json($data);
            }
            return view('mfa::setup.totp', $data);
        } catch (MFAException $e) {
            if (request()->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->route('mfa.totp.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Verify and enable TOTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
        try {
            $validated = $driver->validateSetup($request->all());
            // Verify the code
            $isValid = $driver->verify($user, $validated['code']);
            if ($isValid) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'TOTP has been enabled successfully.',
                    ]);
                }
                return redirect()->route('mfa.totp.index')->with('success', 'TOTP has been enabled successfully. Please save your backup codes.');
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid verification code.',
                ], 422);
            }
            return back()->withErrors(['code' => 'Invalid verification code. Please try again.',])->withInput();
        } catch (MFAException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->withErrors(['code' => $e->getMessage(),])->withInput();
        }
    }

    /**
     * Disable TOTP.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
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
            $message = 'TOTP has been disabled.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }
            return redirect()->route('mfa.totp.index')->with('success', $message);
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
     * Show backup codes.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showBackupCodes(){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
        if (!$driver->isConfigured($user)) {
            $message = 'TOTP is not configured.';
            if (request()->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }
            return redirect()->route('mfa.totp.index')->with('error', $message);
        }
        $backupCodes = $user->getTOTPBackupCodes();
        $data = [
            'backupCodes' => $backupCodes,
            'remainingCount' => count($backupCodes),
        ];
        if (request()->expectsJson() || $this->force_headless) {
            return response()->json($data);
        }
        return view('mfa::management.totp-backup-codes', $data);
    }

    /**
     * Regenerate backup codes.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function regenerateBackupCodes(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('totp');
        try {
            // Require password confirmation
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
            $backupCodes = $driver->regenerateBackupCodes($user);
            $message = 'Backup codes have been regenerated. Please save them securely.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'backup_codes' => $backupCodes,
                ]);
            }
            return redirect()->route('mfa.totp.backup-codes')->with('success', $message);
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
     * Download backup codes as text file.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadBackupCodes(){
        $user = Auth::user();
        $backupCodes = $user->getTOTPBackupCodes();
        $content = "MFA Backup Codes for " . config('app.name') . "\n";
        $content .= "Generated: " . now()->toDateTimeString() . "\n";
        $content .= "User: " . $user->email . "\n\n";
        $content .= "Keep these codes in a safe place. Each code can only be used once.\n\n";        
        foreach ($backupCodes as $index => $code) {
            $content .= ($index + 1) . ". " . $code . "\n";
        }
        return response($content)->header('Content-Type', 'text/plain')->header('Content-Disposition', 'attachment; filename="mfa-backup-codes.txt"');
    }
}