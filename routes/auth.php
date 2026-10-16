<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\PatientSetupLinkController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Public self-registration is CLOSED (Phase 1 security hardening).
    // Both endpoints answer 404 so no account can be created by unauthenticated
    // visitors. Admin-managed staff creation via StaffController is a separate,
    // Admin-only workflow and remains fully functional. The 'register' route
    // name is retained so any existing route('register') references still
    // resolve; the registration view itself is never rendered.
    Route::get('register', fn () => abort(404))->name('register');
    Route::post('register', fn () => abort(404));

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');

    // DEPLA Phase 1.5 — first-time Patient Portal password setup.
    // Strictly single-use, 24-hour, purpose-namespaced token (see
    // PatientSetupTokenService); deliberately separate from the ordinary
    // Forgot Password routes above, which keep their 60-minute lifetime.
    // 'no-store' keeps a token-bearing page out of every cache.
    Route::get('patient-setup/{token}', [PatientSetupLinkController::class, 'show'])
        ->middleware('no-store')
        ->name('patient.setup.show');

    Route::post('patient-setup/{token}', [PatientSetupLinkController::class, 'store'])
        ->middleware('no-store')
        ->name('patient.setup.store');
});

// Phase 2C: 'must-change-password' sits on every authenticated auth route so a
// flagged patient cannot change their password through PUT /password (or any
// other side door) instead of the audited change-initial-password endpoint.
// It only acts on role 'patient', and the middleware exempts 'logout' by name
// so a patient who cannot supply the current password can still sign out.
Route::middleware(['auth', 'must-change-password'])->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
