<?php namespace Mchuluq\LaravelMFA\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Mchuluq\LaravelMFA\MFAManager;
use Mchuluq\LaravelMFA\Exceptions\MFAException;

class WebAuthnController extends Controller{
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
     * Show WebAuthn management page.
     *
     * @return \Illuminate\View\View
     */
    public function index(){
        $user = Auth::user();
        $driver = $this->mfa->driver('webauthn');
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
            return view('mfa::management.webauthn');
        }
    }

    /**
     * Show WebAuthn registration page.
     *
     * @return \Illuminate\View\View
     */
    public function create(){
        if(request()->expectsJson() || $this->force_headless){
            return response()->json(['success' => true]);
        }else{
            return view('mfa::setup.webauthn');
        }
    }

    /**
     * Get registration options.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options(){
        $user = Auth::user();
        $driver = $this->mfa->driver('webauthn');
        try {
            $options = $driver->setup($user);
            return response()->json([
                'success' => true,
                'options' => $options,
            ]);
        } catch (MFAException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Register a new WebAuthn key.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request){
        $user = Auth::user();
        $driver = $this->mfa->driver('webauthn');
        try {
            $validated = $driver->validateSetup($request->all());
            $key = $driver->register(
                $user,
                $validated['credential'],
                $validated['name'] ?? null
            );
            return response()->json([
                'success' => true,
                'message' => 'Security key has been registered successfully.',
                'key' => [
                    'id' => $key->id,
                    'name' => $key->name,
                    'created_at' => $key->created_at->toIso8601String(),
                ],
            ]);
        } catch (MFAException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a WebAuthn key.
     *
     * @param Request $request
     * @param int $keyId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, int $keyId){
        $user = Auth::user();
        $driver = $this->mfa->driver('webauthn');
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
            $deleted = $driver->deleteKey($user, $keyId);
            if (!$deleted) {
                $message = 'Key not found.';
                if ($request->expectsJson() || $this->force_headless) {
                    return response()->json([
                        'success' => false,
                        'message' => $message,
                    ], 404);
                }
                return back()->with('error', $message);
            }
            $message = 'Security key has been deleted.';
            if ($request->expectsJson() || $this->force_headless) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }
            return redirect()->route('mfa.webauthn.index')->with('success', $message);
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
     * Rename a WebAuthn key.
     *
     * @param Request $request
     * @param int $keyId
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $keyId){
        $user = Auth::user();
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);
        $key = $user->webAuthnKeys()->find($keyId);
        if (!$key) {
            $message = 'Key not found.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 404);
            }
            return back()->with('error', $message);
        }
        $key->update(['name' => $validated['name']]);

        $message = 'Security key has been renamed.';
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'key' => [
                    'id' => $key->id,
                    'name' => $key->name,
                ],
            ]);
        }
        return back()->with('success', $message);
    }

    /**
     * Get authentication options.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function authOptions(){
        $user = Auth::user();
        $driver = $this->mfa->driver('webauthn');
        try {
            $options = $driver->challenge($user);
            return response()->json([
                'success' => true,
                'options' => $options,
            ]);
        } catch (MFAException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}