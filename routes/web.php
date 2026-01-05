<?php

use Illuminate\Support\Facades\Route;
use Mchuluq\LaravelMFA\Http\Controllers\MFAChallengeController;
use Mchuluq\LaravelMFA\Http\Controllers\MFAManagementController;
use Mchuluq\LaravelMFA\Http\Controllers\TOTPController;
use Mchuluq\LaravelMFA\Http\Controllers\EMailOTPController;
use Mchuluq\LaravelMFA\Http\Controllers\WebAuthnController;

/*
|--------------------------------------------------------------------------
| MFA Challenge Routes
|--------------------------------------------------------------------------
|
| Routes for MFA challenge and verification during login.
|
*/
Route::name('challenge.')->group(function () {
    Route::get('/challenge', [MFAChallengeController::class, 'index'])->name('index');
    Route::get('/challenge/{driver}', [MFAChallengeController::class, 'show'])->name('show');
    Route::post('/challenge/{driver}', [MFAChallengeController::class, 'verify'])->name('verify');
    Route::post('/challenge/{driver}/resend', [MFAChallengeController::class, 'resend'])->name('resend');
    // Route::post('/challenge/cancel', [MFAChallengeController::class, 'cancel'])->name('cancel');
});

/*
|--------------------------------------------------------------------------
| MFA Management Routes
|--------------------------------------------------------------------------
|
| Routes for managing MFA methods in user settings.
|
*/

Route::name('management.')->prefix('management')->group(function () {
    Route::get('/', [MFAManagementController::class, 'index'])->name('index');
    Route::post('/primary', [MFAManagementController::class, 'setPrimary'])->name('set-primary');
    Route::post('/{driver}/enable', [MFAManagementController::class, 'enable'])->name('enable');
    Route::delete('/{driver}/disable', [MFAManagementController::class, 'disable'])->name('disable');
    Route::delete('/disable-all', [MFAManagementController::class, 'disableAll'])->name('disable-all');
    Route::get('/statistics', [MFAManagementController::class, 'statistics'])->name('statistics');
    // Route::get('/devices', [MFAManagementController::class, 'devices'])->name('devices');
    // Route::post('/devices/forget', [MFAManagementController::class, 'forgetDevice'])->name('devices.forget');
});

/*
|--------------------------------------------------------------------------
| TOTP Routes
|--------------------------------------------------------------------------
|
| Routes for TOTP (Time-based One-Time Password) management.
|
*/
Route::name('totp.')->prefix('totp')->group(function () {
    Route::get('/', [TOTPController::class, 'index'])->name('index');
    Route::get('/setup', [TOTPController::class, 'create'])->name('create');
    Route::post('/setup', [TOTPController::class, 'store'])->name('store');
    Route::delete('/', [TOTPController::class, 'destroy'])->name('destroy');
    Route::get('/backup-codes', [TOTPController::class, 'showBackupCodes'])->name('backup-codes');
    Route::post('/backup-codes/regenerate', [TOTPController::class, 'regenerateBackupCodes'])->name('backup-codes.regenerate');
    Route::get('/backup-codes/download', [TOTPController::class, 'downloadBackupCodes'])->name('backup-codes.download');
});

/*
|--------------------------------------------------------------------------
| Email OTP Routes
|--------------------------------------------------------------------------
|
| Routes for Email OTP management.
|
*/
Route::name('email-otp.')->prefix('email-otp')->group(function () {
    Route::get('/', [EMailOTPController::class, 'index'])->name('index');
    Route::post('/', [EMailOTPController::class, 'store'])->name('store');
    Route::delete('/', [EMailOTPController::class, 'destroy'])->name('destroy');
    Route::post('/test', [EMailOTPController::class, 'sendTest'])->name('test');
});

/*
|--------------------------------------------------------------------------
| WebAuthn Routes
|--------------------------------------------------------------------------
|
| Routes for WebAuthn/Passkey management.
|
*/
Route::name('webauthn.')->prefix('webauthn')->group(function () {
    Route::get('/', [WebAuthnController::class, 'index'])->name('index');
    Route::get('/setup', [WebAuthnController::class, 'create'])->name('create');
    Route::post('/register/options', [WebAuthnController::class, 'options'])->name('register.options');
    Route::post('/register', [WebAuthnController::class, 'store'])->name('register');
    Route::post('/auth/options', [WebAuthnController::class, 'authOptions'])->name('auth.options');
    Route::patch('/{keyId}', [WebAuthnController::class, 'update'])->name('update');
    Route::delete('/{keyId}', [WebAuthnController::class, 'destroy'])->name('destroy');
});