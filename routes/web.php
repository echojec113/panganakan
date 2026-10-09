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
use App\Http\Controllers\PregnancyOutcomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PatientViewController;
use App\Http\Controllers\LocationSearchController;
use App\Http\Controllers\PatientAccountController;
use App\Http\Controllers\PatientInitialPasswordController;
use App\Http\Controllers\PatientPortalController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        // Role-aware landing redirect: clinic staff keep the existing
        // dashboard destination; patient users reach only the neutral
        // holding page; unknown roles are denied outright. No authenticated
        // role can fall through to the clinic dashboard any more.
        return match (auth()->user()->role) {
            'admin', 'staff' => redirect()->route('dashboard'),
            'patient' => redirect()->route('patient.portal.coming-soon'),
            default => abort(403),
        };
    }

    return view('landing');
});

/*
|--------------------------------------------------------------------------
| Patient Portal (Phase 3A — dashboard + read-only My Profile)
|--------------------------------------------------------------------------
| Reachable only by authenticated patient-role users; clinic staff are
| bounced to their dashboard and unknown roles are denied. The route NAME
| (patient.portal.coming-soon) and URL (/patient-portal) are intentionally
| unchanged because Phase 2B/2C login redirects, the guest-middleware
| destination and existing tests all reference them; only the content
| behind the route moved from the holding page to the new dashboard.
|
| Phase 2C boundaries run before the controller: 'patient-portal-account'
| refuses a patient login that is not linked to a live patients row
| (unlinked or archived), then 'must-change-password' sends a flagged
| patient to the mandatory password-change form instead of the portal.
| 'no-store' keeps authenticated portal responses out of any cache.
|
| The new My Profile page carries the exact same chain: identical
| ordering (auth -> eligibility -> workflow -> headers), patient-only
| data, strictly read-only, and no patient ID in the URL.
*/

Route::get('/patient-portal', [PatientPortalController::class, 'dashboard'])
    ->middleware(['auth', 'patient-portal-account', 'must-change-password', 'no-store'])
    ->name('patient.portal.coming-soon');

Route::get('/patient/profile', [PatientPortalController::class, 'profile'])
    ->middleware(['auth', 'patient-portal-account', 'must-change-password', 'no-store'])
    ->name('patient.profile.show');

/*
|--------------------------------------------------------------------------
| Mandatory first-login password change (Phase 2C)
|--------------------------------------------------------------------------
| GET renders the form, POST applies it. Only 'auth' + 'no-store' sit on
| these routes: the flag itself is enforced by the controller and by
| ChangeInitialPasswordRequest::authorize(), because the 'must-change-password'
| middleware must not redirect the very route it protects. Staff/admin
| requests are refused with 403 (patient-only endpoint), and a patient whose
| flag is already cleared is forwarded to the portal without any change.
*/

Route::middleware(['auth', 'no-store'])->group(function () {
    Route::get('/patient/change-initial-password', [PatientInitialPasswordController::class, 'edit'])
        ->name('patient.change-initial-password.edit');

    Route::post('/patient/change-initial-password', [PatientInitialPasswordController::class, 'update'])
        ->name('patient.change-initial-password.update');
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'clinic', 'no-store'])
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

// 'clinic' is the outer deny-by-default boundary: only role 'admin' or
// 'staff' may enter. Existing Admin-only / Staff-only rules further inside
// (middleware and in-controller checks) are unchanged and still apply.
Route::middleware(['auth', 'clinic', 'no-store'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile (view-only for Admin)
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [ProfileController::class, 'edit'])
    ->name('profile.edit');

Route::patch('/profile', [ProfileController::class, 'update'])
    ->name('profile.update');

Route::patch('/profile/photo', [ProfileController::class, 'updatePhoto'])
    ->name('profile.photo.update');

Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])
    ->name('profile.photo.destroy');

    /*
    |--------------------------------------------------------------------------
    | In-app Notifications (authenticated user only, self-scoped)
    |--------------------------------------------------------------------------
    */

    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])
        ->middleware('auth')
        ->name('notifications.markAllRead');

    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])
        ->middleware('auth')
        ->name('notifications.read');

    /*
    |--------------------------------------------------------------------------
    | PSGC Location Search (Admin & Staff) — read-only address autocomplete data
    |--------------------------------------------------------------------------
    */

    Route::get('/locations/search', [LocationSearchController::class, 'search'])
        ->name('locations.search');

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

    Route::get('/patients/{id}/print', [PatientController::class, 'printPatientRecord'])
        ->name('patients.print');

    /*
    |--------------------------------------------------------------------------
    | Prenatal Visit Viewing (Admin & Staff) — read-only, single-visit print
    |--------------------------------------------------------------------------
    */

    Route::get('/prenatal-visits/{visit}/print', [PrenatalVisitController::class, 'print'])
        ->name('prenatal-visits.print');

    /*
    |--------------------------------------------------------------------------
    | Referral Viewing (Admin & Staff)
    |--------------------------------------------------------------------------
    */

    Route::get('/referrals', [ReferralController::class, 'index'])
        ->name('referrals.index');

    Route::get('/referrals/analytics', [ReferralController::class, 'analytics'])
        ->name('referrals.analytics');

    // MUST stay above the `/referrals/{id}` wildcard so "archived" is never
    // resolved as a referral ID. Staff-only, mirroring Prenatal Visits.
    Route::get('/referrals/archived', [ReferralController::class, 'archived'])
        ->middleware('staff')
        ->name('referrals.archived');

    Route::get('/referrals/create', [ReferralController::class, 'selectPatient'])
        ->middleware('staff')
        ->name('referrals.select-patient');

    Route::get('/referrals/{id}/print', [ReferralController::class, 'print'])
        ->name('referrals.print');

    // Optional supporting image recorded with a refusal. Static suffix on a
    // two-segment path, so it can never collide with the single-segment
    // /referrals/{id} wildcard below. Auth only (no public storage URL).
    Route::get('/referrals/{referral}/refusal-image', [ReferralController::class, 'refusalImage'])
        ->name('referrals.refusal-image');

    Route::get('/referrals/{id}', [ReferralController::class, 'show'])
        ->name('referrals.show');

    /*
    |--------------------------------------------------------------------------
    | Monitoring / Logs (Admin & Staff)
    |--------------------------------------------------------------------------
    */

    Route::get('/risk-monitoring', [RiskMonitoringController::class, 'index'])
        ->middleware(\App\Http\Middleware\AdminMiddleware::class)
        ->name('risk.monitoring');

    Route::get('/risk-monitoring/analytics', [RiskMonitoringController::class, 'analytics'])
        ->name('risk.monitoring.analytics');

    Route::get('/pregnancy-outcomes', [PregnancyOutcomeController::class, 'index'])
        ->name('pregnancy-outcomes.index');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    /*
    |--------------------------------------------------------------------------
    | View All Records (Admin-only via controller check)
    |--------------------------------------------------------------------------
    */

    Route::get('/view-all-records', [PatientViewController::class, 'index'])
        ->name('view-all-records.index');

    Route::get('/view-all-records/{patient}/history', [PatientViewController::class, 'history'])
        ->name('view-all-records.history');

    Route::get('/view-all-records/{patient}/pregnancy/print', [PatientViewController::class, 'print'])
        ->name('view-all-records.pregnancy.print');

    Route::get('/view-all-records/{patient}/pregnancy', [PatientViewController::class, 'pregnancy'])
        ->name('view-all-records.pregnancy');

    /*
    |--------------------------------------------------------------------------
    | Staff Management (Admin-only via controller check)
    |--------------------------------------------------------------------------
    */

    Route::get('/staff/archived', [StaffController::class, 'archived'])
        ->name('staff.archived');

    Route::post('/staff/{id}/restore', [StaffController::class, 'restore'])
        ->name('staff.restore');

    Route::post('/staff/{staff}/reset-account', [StaffController::class, 'resetAccount'])
        ->name('staff.reset-account');

    Route::resource('staff', StaffController::class)->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | Patient Login Account Provisioning (Admin & Staff) — DEPLA Phase 2B
    |--------------------------------------------------------------------------
    | Scoped to 'auth' + 'clinic' only: BOTH clinic roles (admin and staff)
    | may provision a portal login for an existing patient record, exactly as
    | Phase 2B specifies. It is deliberately kept OUTSIDE the staff-only
    | clinical write group below, which excludes Admin.
    |
    | The endpoint creates one users row, sets patients.user_id and writes one
    | audit entry - it never creates a patient and never touches clinical
    | data, so the staff-only restriction that guards clinical writes does not
    | apply to it. No GET variant exists: there is no account-creation page.
    */

    Route::post('/patients/{patient}/account', [PatientAccountController::class, 'store'])
        ->name('patients.account.store');

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

        Route::post('/patients/{id}/pregnancy-outcome/still-pregnant', [PregnancyOutcomeController::class, 'recordStillPregnant'])
            ->name('pregnancy-outcomes.still-pregnant');

        Route::post('/patients/{id}/pregnancy-outcome/unable-to-contact', [PregnancyOutcomeController::class, 'recordUnableToContact'])
            ->name('pregnancy-outcomes.unable-to-contact');

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

        Route::post('/referrals/{id}/refuse', [ReferralController::class, 'refuse'])
            ->name('referrals.refuse');

        Route::post('/referrals/{id}/cancel', [ReferralController::class, 'cancel'])
            ->name('referrals.cancel');

        Route::post('/referrals/{id}/restore', [ReferralController::class, 'restoreArchived'])
            ->name('referrals.restore');

        Route::delete('/referrals/{id}', [ReferralController::class, 'destroy'])
            ->name('referrals.destroy');

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

        Route::get('/prenatal-visits/archived', [PrenatalVisitController::class, 'archived'])
            ->name('prenatal-visits.archived');

        Route::post('/prenatal-visits/{id}/restore', [PrenatalVisitController::class, 'restoreArchived'])
            ->name('prenatal-visits.restore');

        Route::resource('prenatal-visits', PrenatalVisitController::class);
        Route::resource('medical-histories', MedicalHistoryController::class);
        Route::resource('birth-plans', BirthPlanController::class);
    });

    /*
    |--------------------------------------------------------------------------
    | Ultrasound File Viewing (Admin & Staff)
    |--------------------------------------------------------------------------
    */

    Route::get('/ultrasound/{id}/file/{type}', [UltrasoundController::class, 'file'])
        ->where('type', 'image|pdf')
        ->name('ultrasound.file');

    /*
    |--------------------------------------------------------------------------
    | Wildcard patient route MUST be last to avoid catching /patients/create etc.
    |--------------------------------------------------------------------------
    */

    Route::get('/patients/{patient}', [PatientController::class, 'show'])
        ->name('patients.show');
});

require __DIR__.'/auth.php';
