<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PatientSetupTokenService;
use App\Services\PatientSessionRevocationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * DEPLA Phase 2 - patient session + token revocation on deactivate/reactivate.
 *
 * Covers the ten mandated guarantees:
 *  1. Session invalid after deactivation, still invalid after reactivation,
 *     fresh login required - verified with the DATABASE session driver.
 *  2. Remember-me recaller replay rejected after reactivation.
 *  3. Old password-reset link rejected after reactivation.
 *  4. Old first-time-setup link rejected after reactivation.
 *  5. Admin and Staff sessions untouched (rows, remember tokens, requests).
 *  6. Repeated deactivate/reactivate stays safe and predictable.
 *  7. Patient / pregnancy / clinical records unchanged across a cycle.
 *  8. Session-row deletion is scoped strictly to the target user.
 *  9. The middleware is a no-op for guests and non-patients, and fails
 *     closed for a patient session that predates the revocation.
 * 10. Revocation proven on the database driver, not only the array driver.
 *
 * Every case runs against the isolated SQLite :memory: database with the
 * array mailer - no real email is ever sent.
 */

beforeEach(function () {
    $this->withoutVite();
});

/** Switch the session driver to the database store for this test. */
function pa2revUseDatabaseSessions(): void
{
    config(['session.driver' => 'database']);
    app('session')->setDefaultDriver('database');
}

function pa2revPatient(string $first = 'Rev', string $last = 'Ocation', string $email = 'rev.patient@phase2rev.test'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 26,
        'address' => 'Phase 2 revocation test address',
        'contact_number' => '09170003333',
        'email' => $email,
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function pa2revAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function pa2revStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/** Provision through the REAL Phase 2B/1.5 endpoint. */
function pa2revProvision(Patient $patient, string $loginEmail, ?User $actor = null): User
{
    $actor ??= pa2revStaff();

    $response = test()->actingAs($actor)->post(
        route('patients.account.store', $patient->id),
        ['email' => $loginEmail]
    );

    $response->assertRedirect(route('patients.show', $patient->id));

    app('auth')->forgetGuards();

    return User::where('email', $loginEmail)->firstOrFail();
}

/** Simulate the patient completing the emailed setup. */
function pa2revActivate(User $user): User
{
    $user->must_change_password = false;
    $user->save();

    return $user;
}

/** Set a known password so the real login form can be used. */
function pa2revSetPassword(User $user, string $password = 'known-revocation-password'): User
{
    $user->password = Hash::make($password);
    $user->save();

    return $user;
}

/**
 * Build a linked, setup-complete patient account directly (no actingAs), so a
 * subsequent real login is the FIRST guard interaction of the test and the
 * database session row is written with the patient's own user_id (matching
 * production, where no prior staff actingAs precedes a patient login).
 */
function pa2revCreateLinkedAccount(Patient $patient, string $loginEmail, string $password = 'known-revocation-password'): User
{
    $user = User::factory()->create([
        'role' => 'patient',
        'email' => $loginEmail,
        'password' => Hash::make($password),
    ]);

    $user->must_change_password = false;
    $user->save();

    $patient->forceFill(['user_id' => $user->id])->save();

    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_at = now();
    $link->save();

    return $user;
}

/** Compute the remember-me recaller cookie name the framework uses. */
function pa2revRecallerName(): string
{
    return 'remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class);
}

/*
|--------------------------------------------------------------------------
| 1 + 10. Database-driver session revocation (not only the array driver)
|--------------------------------------------------------------------------
*/

it('revokes a live session on deactivation and refuses it after reactivation, requiring a fresh login (database driver)', function () {
    pa2revUseDatabaseSessions();
    Mail::fake();

    $patient = pa2revPatient('Db', 'Session', 'db.session@phase2rev.test');
    $account = pa2revCreateLinkedAccount($patient, 'db.session.login@phase2rev.test');

    // 1. Real login: a sessions row is written for the patient.
    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-revocation-password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->assertAuthenticatedAs($account);
    expect(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(1);

    // 2. The admin deactivates the account.
    $admin = pa2revAdmin();
    $this->actingAs($admin)
        ->post(route('patient-accounts.deactivate', $account))
        ->assertSessionHas('success');
    app('auth')->forgetGuards();

    // The patient's session row is gone from the database.
    expect(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(0);

    // The old session cookie is refused (SoftDeletingScope + middleware).
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    // 3. Reactivate: the account is live again but the old session stays dead.
    $this->actingAs($admin)->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    expect(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(0);
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    // 4. A fresh login works again.
    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-revocation-password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->assertAuthenticatedAs($account->fresh());
});

/*
|--------------------------------------------------------------------------
| 8. Session-row deletion is scoped strictly to the target user
|--------------------------------------------------------------------------
*/

it('deletes only the target patient session rows and leaves other users sessions intact', function () {
    pa2revUseDatabaseSessions();
    Mail::fake();

    $patient = pa2revPatient('Scoped', 'Rows', 'scoped.rows@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'scoped.rows.login@phase2rev.test'));

    $admin = pa2revAdmin();
    $staff = pa2revStaff();

    $now = time();
    DB::table('sessions')->insert([
        ['id' => 'patient-session-a', 'user_id' => $account->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
        ['id' => 'patient-session-b', 'user_id' => $account->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
        ['id' => 'staff-session-a', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
        ['id' => 'admin-session-a', 'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
    ]);

    $this->actingAs($admin)
        ->post(route('patient-accounts.deactivate', $account))
        ->assertSessionHas('success');

    // Both patient rows are gone; the staff and pre-existing admin rows stay.
    expect(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $staff->id)->count())->toBe(1)
        ->and(DB::table('sessions')->where('id', 'admin-session-a')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 2. Remember-me recaller replay is rejected after reactivation
|--------------------------------------------------------------------------
*/

it('rejects an old remember-me recaller cookie after deactivation and reactivation (database driver)', function () {
    pa2revUseDatabaseSessions();
    Mail::fake();

    $patient = pa2revPatient('Remember', 'Replay', 'remember.replay@phase2rev.test');
    $account = pa2revCreateLinkedAccount($patient, 'remember.replay.login@phase2rev.test');

    $rememberTokenBefore = $account->remember_token;

    // 1. Real login with "remember me".
    $login = $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-revocation-password',
        'remember' => 1,
    ]);
    $login->assertRedirect(route('patient.portal.coming-soon'));

    $recallerName = pa2revRecallerName();
    $recallerValue = $login->getCookie($recallerName, false);
    expect($recallerValue)->not->toBeNull();

    // 2. Deactivate + reactivate.
    $admin = pa2revAdmin();
    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    // The remember token was rotated, so the old recaller payload is dead.
    expect($account->fresh()->remember_token)->not->toBe($rememberTokenBefore);

    // 3. Replay ONLY the old recaller cookie (no session cookie): the guard's
    //    userFromRecaller fails the rotated token and resolves no user, so the
    //    patient portal redirects to login instead of resurrecting access.
    $this->withUnencryptedCookie($recallerName, $recallerValue)
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

/*
|--------------------------------------------------------------------------
| 3. Old password-reset link is rejected after reactivation
|--------------------------------------------------------------------------
*/

it('rejects an old password-reset link after deactivation and reactivation', function () {
    Mail::fake();
    Notification::fake();

    $patient = pa2revPatient('Reset', 'Purge', 'reset.purge@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'reset.purge.login@phase2rev.test'));
    pa2revSetPassword($account);

    $admin = pa2revAdmin();

    // 1. The PATIENT requests their own reset link through the untouched
    //    Forgot Password flow (guest-only route, so no actingAs here);
    //    capture the emailed token.
    $this->post(route('password.email'), ['email' => $account->email])
        ->assertSessionHas('status');

    $token = null;
    Notification::assertSentTo(
        $account,
        ResetPassword::class,
        function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        }
    );
    expect($token)->not->toBeNull();
    expect(DB::table('password_reset_tokens')->where('email', $account->email)->count())->toBe(1);

    // 2. Deactivate + reactivate.
    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    // The reset token row was purged.
    expect(DB::table('password_reset_tokens')->where('email', $account->email)->count())->toBe(0);

    // 3. The old link's token can no longer change the password.
    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $account->email,
        'password' => 'brand-new-revocation-password',
        'password_confirmation' => 'brand-new-revocation-password',
    ]);

    expect(Hash::check('brand-new-revocation-password', $account->fresh()->password))->toBeFalse()
        ->and(Hash::check('known-revocation-password', $account->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 4. Old first-time-setup link is rejected after reactivation
|--------------------------------------------------------------------------
*/

it('rejects an old setup link after deactivation and reactivation', function () {
    Mail::fake();

    $patient = pa2revPatient('Setup', 'Purge', 'setup.purge@phase2rev.test');
    $pending = pa2revProvision($patient, 'setup.purge.login@phase2rev.test');

    // 1. Create a first-time-setup token while the account is pending.
    $token = app(PatientSetupTokenService::class)->create($pending);
    expect(DB::table('password_reset_tokens')->where('email', PatientSetupTokenService::KEY_PREFIX.$pending->id)->count())->toBe(1);

    $admin = pa2revAdmin();

    // 2. Deactivate + reactivate.
    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $pending))->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $pending))->assertSessionHas('success');
    app('auth')->forgetGuards();

    // The namespaced setup token row was purged.
    expect(DB::table('password_reset_tokens')->where('email', PatientSetupTokenService::KEY_PREFIX.$pending->id)->count())->toBe(0);

    // 3. The old setup link can no longer set the password.
    $this->post(route('patient.setup.store', $token), [
        'email' => $pending->email,
        'password' => 'brand-new-setup-password',
        'password_confirmation' => 'brand-new-setup-password',
    ]);

    expect(Hash::check('brand-new-setup-password', $pending->fresh()->password))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 5. Admin and Staff sessions remain valid and untouched
|--------------------------------------------------------------------------
*/

it('leaves admin and staff sessions, remember tokens and requests untouched when a patient is deactivated', function () {
    pa2revUseDatabaseSessions();
    Mail::fake();

    $patient = pa2revPatient('Isolate', 'Roles', 'isolate.roles@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'isolate.roles.login@phase2rev.test'));

    $admin = pa2revAdmin();
    $staff = pa2revStaff();

    $adminTokenBefore = $admin->remember_token;
    $staffTokenBefore = $staff->remember_token;

    $now = time();
    DB::table('sessions')->insert([
        ['id' => 'iso-admin', 'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
        ['id' => 'iso-staff', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => 'x', 'last_activity' => $now],
    ]);

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    // Admin and Staff session rows survive; their remember tokens never move.
    expect(DB::table('sessions')->where('user_id', $admin->id)->count())->toBeGreaterThanOrEqual(1)
        ->and(DB::table('sessions')->where('id', 'iso-staff')->count())->toBe(1)
        ->and($admin->fresh()->remember_token)->toBe($adminTokenBefore)
        ->and($staff->fresh()->remember_token)->toBe($staffTokenBefore);

    // Their requests still work: the admin listing renders and the staff
    // guard is unaffected by the patient revocation marker.
    $this->actingAs($admin)->get(route('patient-accounts.index'))->assertOk();
    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| 6. Repeated lifecycle actions stay safe and audit-free of duplicates
|--------------------------------------------------------------------------
*/

it('keeps repeated deactivate/reactivate predictable with a single audit row per action and no extra revocation writes', function () {
    Mail::fake();

    $patient = pa2revPatient('Repeat', 'Rev', 'repeat.rev@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'repeat.rev.login@phase2rev.test'));
    $passwordBefore = $account->password;

    $admin = pa2revAdmin();

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');

    // Second deactivate is refused before any revocation runs.
    $tokenAfterFirst = $account->fresh()->remember_token;
    $this->post(route('patient-accounts.deactivate', $account))
        ->assertSessionHas('error', 'This account is already deactivated. Reactivate it to restore patient access.');
    expect($account->fresh()->remember_token)->toBe($tokenAfterFirst);

    $this->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');

    $tokenAfterReactivate = $account->fresh()->remember_token;
    $this->post(route('patient-accounts.reactivate', $account))
        ->assertSessionHas('error', 'This account is already active. No changes were made.');
    expect($account->fresh()->remember_token)->toBe($tokenAfterReactivate);

    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'PATIENT_ACCOUNT_REACTIVATED')->count())->toBe(1)
        ->and($account->fresh()->password)->toBe($passwordBefore)
        ->and($account->fresh()->trashed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 7. Patient / pregnancy / clinical records are never touched
|--------------------------------------------------------------------------
*/

it('never changes the patient, pregnancy link or account pointer across a revocation cycle', function () {
    Mail::fake();

    $patient = pa2revPatient('Records', 'Keeper', 'records.keeper@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'records.keeper.login@phase2rev.test'));
    pa2revSetPassword($account);

    $sibling = pa2revPatient('Sibling', 'Link', 'sibling.link@phase2rev.test');
    $link = new UserPregnancyLink();
    $link->user_id = $account->id;
    $link->patient_id = $sibling->id;
    $link->verified_at = now();
    $link->save();

    $admin = pa2revAdmin();

    $before = [
        'users' => User::count(),
        'patients' => Patient::count(),
        'links' => UserPregnancyLink::count(),
        'pointer' => $patient->fresh()->user_id,
        'password' => $account->fresh()->password,
        'flag' => (bool) $account->fresh()->must_change_password,
    ];

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');

    $account->refresh();

    expect(User::count())->toBe($before['users'])
        ->and(Patient::count())->toBe($before['patients'])
        ->and(UserPregnancyLink::count())->toBe($before['links'])
        ->and($patient->fresh()->user_id)->toBe($before['pointer'])
        ->and($account->password)->toBe($before['password'])
        ->and((bool) $account->must_change_password)->toBe($before['flag'])
        ->and($account->trashed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 9. Middleware: no-op for guests / non-patients, fails closed for patients
|--------------------------------------------------------------------------
*/

it('passes guests and non-patient users through the revocation middleware untouched', function () {
    // Guest: the middleware resolves no user and never touches the session.
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    // A patient who has never been revoked is never logged out.
    $patient = pa2revPatient('Never', 'Revoked', 'never.revoked@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'never.revoked.login@phase2rev.test'));
    pa2revSetPassword($account);

    $this->actingAs($account)->get(route('patient.portal.coming-soon'))->assertOk();
});

it('fails closed for a patient session that predates a recorded revocation', function () {
    Mail::fake();

    $patient = pa2revPatient('Stale', 'Marker', 'stale.marker@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'stale.marker.login@phase2rev.test'));

    // Record a revocation for this patient.
    app(PatientSessionRevocationService::class)->markRevoked($account);

    // A session with no auth marker (pre-feature or stale) is treated as 0
    // and rejected by the middleware.
    $this->actingAs($account)
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('lets a freshly authenticated patient session through after a recorded revocation', function () {
    Mail::fake();

    $patient = pa2revPatient('Fresh', 'Marker', 'fresh.marker@phase2rev.test');
    $account = pa2revActivate(pa2revProvision($patient, 'fresh.marker.login@phase2rev.test'));

    app(PatientSessionRevocationService::class)->markRevoked($account);

    // A session whose auth marker is at or after the revocation timestamp is
    // honored - this is what a fresh login (or remember-me re-auth) writes.
    $this->withSession([PatientSessionRevocationService::SESSION_MARKER_KEY => microtime(true) + 60])
        ->actingAs($account)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk();
});
