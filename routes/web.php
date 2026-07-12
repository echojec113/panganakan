<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PrenatalVisitController;
use App\Http\Controllers\MedicalHistoryController;
use App\Http\Controllers\BirthPlanController;
use App\Http\Controllers\UltrasoundController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\RiskMonitoringController;
use App\Http\Controllers\ReferralController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile (view-only for Admin)
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | View-Only Patient Access (Admin & Staff)
    | Specific routes MUST come before wildcard {patient}
    |--------------------------------------------------------------------------
    */

    Route::get('/patients/delivered', [PatientController::class, 'delivered'])
        ->name('patients.delivered');

    Route::get('/patients/delivered/{id}/history', [PatientController::class, 'pregnancyHistory'])
        ->name('patients.delivered.history');

    Route::get('/patients/delivered/{id}/babies', [PatientController::class, 'babyInformation'])
        ->name('patients.delivered.babies');

    Route::get('/patients/delivered/{id}/print-babies', [PatientController::class, 'printBabies'])
        ->name('patients.delivered.print-babies');

    Route::post('/patients/{id}/download', [PatientController::class, 'download'])
        ->name('patients.download');

    /*
    |--------------------------------------------------------------------------
    | Referral Viewing (Admin & Staff)
    |--------------------------------------------------------------------------
    */

    Route::get('/referrals', [ReferralController::class, 'index'])
        ->name('referrals.index');

    Route::get('/referrals/{id}/print', [ReferralController::class, 'print'])
        ->name('referrals.print');

    /*
    |--------------------------------------------------------------------------
    | Monitoring / Logs (Admin & Staff)
    |--------------------------------------------------------------------------
    */

    Route::get('/risk-monitoring', [RiskMonitoringController::class, 'index'])
        ->name('risk.monitoring');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    /*
    |--------------------------------------------------------------------------
    | Staff Management (Admin-only via controller check)
    |--------------------------------------------------------------------------
    */

    Route::resource('staff', StaffController::class)->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | Clinical Write Routes (Staff-only)
    |--------------------------------------------------------------------------
    */

    Route::middleware('staff')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Patient Write Routes
        |--------------------------------------------------------------------------
        */

        Route::resource('patients', PatientController::class)->except(['show']);

        Route::get('/patients/trashed', [PatientController::class, 'trashed'])
            ->name('patients.trashed');

        Route::post('/patients/{id}/restore', [PatientController::class, 'restore'])
            ->name('patients.restore');

        Route::post('/patients/{id}/deliver', [PatientController::class, 'markDelivered'])
            ->name('patients.deliver');

        Route::post('/patients/babies/{id}', [PatientController::class, 'updateBaby'])
            ->name('patients.update-baby');

        Route::post('/patients/{id}/start-new-pregnancy', [PatientController::class, 'startNewPregnancy'])
            ->name('patients.start-new-pregnancy');

        /*
        |--------------------------------------------------------------------------
        | Referral Write Routes
        |--------------------------------------------------------------------------
        */

        Route::get('/patients/{id}/referral/create', [ReferralController::class, 'create'])
            ->name('referrals.create');

        Route::post('/referrals/store', [ReferralController::class, 'store'])
            ->name('referrals.store');

        Route::post('/referrals/{id}/complete', [ReferralController::class, 'complete'])
            ->name('referrals.complete');

        /*
        |--------------------------------------------------------------------------
        | Ultrasound
        |--------------------------------------------------------------------------
        */

        Route::get('/ultrasound/create/{patient}', [UltrasoundController::class, 'create'])
            ->name('ultrasound.create');

        Route::post('/ultrasound/store', [UltrasoundController::class, 'store'])
            ->name('ultrasound.store');

        Route::get('/ultrasound/{id}/edit', [UltrasoundController::class, 'edit'])
            ->name('ultrasound.edit');

        Route::put('/ultrasound/{id}', [UltrasoundController::class, 'update'])
            ->name('ultrasound.update');

        /*
        |--------------------------------------------------------------------------
        | Write Resources
        |--------------------------------------------------------------------------
        */

        Route::resource('prenatal-visits', PrenatalVisitController::class);
        Route::resource('medical-histories', MedicalHistoryController::class);
        Route::resource('birth-plans', BirthPlanController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Wildcard patient route MUST be last to avoid catching /patients/create etc.
    |--------------------------------------------------------------------------
    */

    Route::get('/patients/{patient}', [PatientController::class, 'show'])
        ->name('patients.show');
});

require __DIR__.'/auth.php';
