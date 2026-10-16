<?php

use App\Mail\PatientAccountSetupMail;
use App\Models\AuditLog;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PatientSetupTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * DEPLA Phase 2 Sprint 2 - Deactivate / Reactivate patient portal accounts.
 *
 * Covers: admin-only authorization (route + middleware + controller) on the
 * two new lifecycle endpoints, SoftDeletes-only writes (no hard delete,
 * no column changes), the audit trail (PATIENT_ACCOUNT_DEACTIVATED /
 * PATIENT_ACCOUNT_REACTIVATED, ID-only descriptions), repeated-submission
 * handling, query-string-preserving redirects, the status doctrine round
 * trip (Active and Pending both return to their pre-cycle status), the UI
 * affordances (Deactivate on live rows, Reactivate on deactivated rows,
 * danger/success modal variants), and the access-denial guarantees: login
 * refused while deactivated, an already authenticated session denied on its
 * next request, setup links and password resets refused, AND - because the
 * lifecycle controller now actively revokes live credentials - a session
 * that was alive before reactivation is still refused and a fresh login is
 * required.
 *
 * Every case runs against the isolated SQLite :memory: database with the
 * array mailer - no real email is ever sent.
 */

beforeEach(function () {
    $this->withoutVite();
});

function pa2s2Patient(string $first = 'Ana', string $last = 'Santos', string $email = 'patient@phase2s2.test'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 27,
        'address' => 'Phase 2 Sprint 2 test address',
        'contact_number' => '09170002222',
        'email' => $email,
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function pa2s2Admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function pa2s2Staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/**
 * Provision through the REAL Phase 2B/1.5 endpoint so the account carries
 * its pointer, its verified pregnancy link and its pending-setup flag
 * exactly as production does.
 */
function pa2s2Provision(Patient $patient, string $loginEmail, ?User $actor = null): User
{
    $actor ??= pa2s2Staff();

    $response = test()->actingAs($actor)->post(
        route('patients.account.store', $patient->id),
        ['email' => $loginEmail]
    );

    $response->assertRedirect(route('patients.show', $patient->id));

    app('auth')->forgetGuards();

    return User::where('email', $loginEmail)->firstOrFail();
}

/** Simulate the patient completing the emailed setup (flag cleared, login usable). */
function pa2s2Activate(User $user): User
{
    $user->must_change_password = false;
    $user->save();

    return $user;
}

/** Explicit, ownership-evidence link row (never mass assigned). */
function pa2s2Link(User $user, Patient $patient): UserPregnancyLink
{
    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_by = null;
    $link->verified_at = now();
    $link->save();

    return $link;
}

/*
|--------------------------------------------------------------------------
| 1-2. Access: guest / staff / patient / non-patient targets
|--------------------------------------------------------------------------
*/

it('redirects a guest to login from both lifecycle routes', function () {
    $this->post(route('patient-accounts.deactivate', 1))->assertRedirect(route('login'));
    $this->post(route('patient-accounts.reactivate', 1))->assertRedirect(route('login'));
});

it('denies staff and patient users on both lifecycle endpoints', function () {
    Mail::fake();

    $target = pa2s2Activate(pa2s2Provision(pa2s2Patient('Auth', 'Life', 'auth.life@phase2s2.test'), 'auth.life.login@phase2s2.test'));

    foreach ([pa2s2Staff(), User::factory()->create(['role' => 'patient'])] as $intruder) {
        $this->actingAs($intruder)->post(route('patient-accounts.deactivate', $target))->assertForbidden();
        $this->post(route('patient-accounts.reactivate', $target))->assertForbidden();
    }

    expect($target->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['PATIENT_ACCOUNT_DEACTIVATED', 'PATIENT_ACCOUNT_REACTIVATED'])->count())->toBe(0);
});

it('refuses lifecycle attempts against non-patient account ids', function () {
    Mail::fake();
    $admin = pa2s2Admin();
    $staffTarget = pa2s2Staff();

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $staffTarget))->assertNotFound();
    $this->post(route('patient-accounts.reactivate', $staffTarget))->assertNotFound();

    expect($staffTarget->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['PATIENT_ACCOUNT_DEACTIVATED', 'PATIENT_ACCOUNT_REACTIVATED'])->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 3-4. Deactivate an Active account
|--------------------------------------------------------------------------
*/

it('deactivates an active account with one audit row and a query-preserving redirect', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $patient = pa2s2Patient('Active', 'Deact', 'active.deact@phase2s2.test');
    $account = pa2s2Activate(pa2s2Provision($patient, 'active.deact.login@phase2s2.test'));

    $passwordBefore = $account->password;

    $this->actingAs($admin)
        ->post(route('patient-accounts.deactivate', array_merge(['account' => $account], ['status' => 'active', 'search' => 'Deact'])))
        ->assertRedirect(route('patient-accounts.index', ['status' => 'active', 'search' => 'Deact']))
        ->assertSessionHas('success');

    $account->refresh();

    expect($account->trashed())->toBeTrue()
        // Nothing but deleted_at moved.
        ->and($account->password)->toBe($passwordBefore)
        ->and((bool) $account->must_change_password)->toBeFalse()
        ->and($account->email_verified_at)->toBeNull()
        ->and($patient->fresh()->user_id)->toBe($account->id);

    $audit = AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')
        ->where('module', 'PATIENT')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->patient_id)->toBe($patient->id)
        ->and($audit->description)->toContain('user ID '.$account->id)
        // ID-only description: never the email, the name or any clinical value.
        ->and($audit->description)->not->toContain('@')
        ->and($audit->description)->not->toContain('Deact');

    // Only one audit row for the whole action.
    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 5. Pending round trip: must_change_password is never flipped
|--------------------------------------------------------------------------
*/

it('deactivates a pending account and returns it to Pending Setup after reactivation', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $pending = pa2s2Provision(
        pa2s2Patient('Still', 'Pending', 'still.pending@phase2s2.test'),
        'still.pending.login@phase2s2.test'
    );

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $pending))
        ->assertSessionHas('success');

    expect($pending->fresh()->trashed())->toBeTrue()
        ->and((bool) $pending->fresh()->must_change_password)->toBeTrue();

    $this->post(route('patient-accounts.reactivate', $pending))
        ->assertSessionHas('success');

    $pending->refresh();

    expect($pending->trashed())->toBeFalse()
        ->and((bool) $pending->must_change_password)->toBeTrue();

    // The listing reflects the doctrine: Pending Setup badge, not Active.
    $this->get(route('patient-accounts.index'))
        ->assertOk()
        ->assertSee('data-status="pending-setup"', false);
});

/*
|--------------------------------------------------------------------------
| 6. Reactivate an Active account
|--------------------------------------------------------------------------
*/

it('reactivates a deactivated account with one audit row', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $account = pa2s2Activate(pa2s2Provision(
        pa2s2Patient('Restore', 'Me', 'restore.me@phase2s2.test'),
        'restore.me.login@phase2s2.test'
    ));
    $account->delete();

    $passwordBefore = $account->password;

    $this->actingAs($admin)
        ->post(route('patient-accounts.reactivate', $account))
        ->assertRedirect(route('patient-accounts.index'))
        ->assertSessionHas('success');

    $account->refresh();

    expect($account->trashed())->toBeFalse()
        ->and($account->password)->toBe($passwordBefore)
        ->and((bool) $account->must_change_password)->toBeFalse();

    $audit = AuditLog::where('action', 'PATIENT_ACCOUNT_REACTIVATED')
        ->where('module', 'PATIENT')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->patient_id)->toBe($account->patientAccount()->value('id'))
        ->and($audit->description)->toContain('user ID '.$account->id)
        ->and($audit->description)->not->toContain('@');

    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_REACTIVATED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')->count())->toBe(0);

    $this->get(route('patient-accounts.index'))
        ->assertOk()
        ->assertSee('data-status="active"', false);
});

/*
|--------------------------------------------------------------------------
| 7. Repeated submissions are safe, predictable and audit-free
|--------------------------------------------------------------------------
*/

it('refuses repeated deactivation and reactivation with predictable flashes and no duplicate audit', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $account = pa2s2Activate(pa2s2Provision(
        pa2s2Patient('Repeat', 'Sub', 'repeat.sub@phase2s2.test'),
        'repeat.sub.login@phase2s2.test'
    ));

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))
        ->assertSessionHas('success');

    // Second deactivate: error flash, still one audit row.
    $this->post(route('patient-accounts.deactivate', $account))
        ->assertRedirect(route('patient-accounts.index'))
        ->assertSessionHas('error', 'This account is already deactivated. Reactivate it to restore patient access.');

    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')->count())->toBe(1)
        ->and($account->fresh()->trashed())->toBeTrue();

    $this->post(route('patient-accounts.reactivate', $account))
        ->assertSessionHas('success');

    // Second reactivate: error flash, still one audit row.
    $this->post(route('patient-accounts.reactivate', $account))
        ->assertSessionHas('error', 'This account is already active. No changes were made.');

    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_REACTIVATED')->count())->toBe(1)
        ->and($account->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['PATIENT_ACCOUNT_DEACTIVATED', 'PATIENT_ACCOUNT_REACTIVATED'])->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 8-9. Access denial: login, live session, setup links, reset links
|--------------------------------------------------------------------------
*/

it('blocks authentication for a deactivated account and restores it after reactivation', function () {
    Mail::fake();
    Notification::fake();

    $patient = pa2s2Patient('Login', 'Block', 'login.block@phase2s2.test');
    $account = pa2s2Activate(pa2s2Provision($patient, 'login.block.login@phase2s2.test'));

    // Set a known password directly (production never does this; it is only
    // for asserting the login gate in isolation).
    $account->password = \Illuminate\Support\Facades\Hash::make('known-password');
    $account->save();

    $admin = pa2s2Admin();
    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-password',
    ])->assertSessionHasErrors('email');

    // Ordinary password reset is refused too (SoftDeletingScope on the broker).
    $this->post(route('password.email'), ['email' => $account->email]);
    Notification::assertNothingSent();

    // Setup token consumption refuses a deactivated account (live-account recheck).
    $token = app(PatientSetupTokenService::class)->create($account);
    $this->post(route('patient.setup.store', $token), [
        'email' => $account->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertRedirect(route('login'));

    expect(\Illuminate\Support\Facades\Hash::check('brand-new-password', $account->fresh()->password))->toBeFalse();

    // Reactivate: login works again.
    $this->actingAs($admin)->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->assertAuthenticatedAs($account->fresh());
});

it('denies an already authenticated session on its next request after deactivation', function () {
    Mail::fake();

    $patient = pa2s2Patient('Session', 'Kick', 'session.kick@phase2s2.test');
    $account = pa2s2Activate(pa2s2Provision($patient, 'session.kick.login@phase2s2.test'));
    $account->password = \Illuminate\Support\Facades\Hash::make('known-password');
    $account->save();

    // 1. The patient signs in for real (session guard, not actingAs).
    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->assertAuthenticatedAs($account);

    // 2. The admin deactivates the account in another "tab".
    $admin = pa2s2Admin();
    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    // 3. The patient's next request: the session cookie is still present,
    //    but the provider's SoftDeletingScope resolves no user, so the
    //    'auth' middleware redirects to login.
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    // 4. Reactivate: the SAME session is still refused. The lifecycle
    //    controller actively revoked every live credential (session rows,
    //    remember token, reset/setup tokens) and wrote a cache revocation
    //    marker, so the driver-independent middleware requires a fresh
    //    login - the account can no longer be resurrected by an old cookie.
    $this->actingAs($admin)->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');
    app('auth')->forgetGuards();

    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    // 5. A fresh login works again.
    $this->post(route('login'), [
        'email' => $account->email,
        'password' => 'known-password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->assertAuthenticatedAs($account->fresh());
});

it('refuses admin account-management links and setup links for a deactivated account', function () {
    Mail::fake();
    Notification::fake();

    $patient = pa2s2Patient('Archived', 'Portal', 'archived.portal@phase2s2.test');
    $account = pa2s2Provision($patient, 'archived.portal.login@phase2s2.test');
    $account->delete();

    $admin = pa2s2Admin();

    // The lifecycle route still resolves a trashed account (withTrashed) and
    // refuses a repeated submission with the predictable flash.
    $this->actingAs($admin)
        ->post(route('patient-accounts.deactivate', $account))
        ->assertSessionHas('error', 'This account is already deactivated. Reactivate it to restore patient access.');

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertSessionHas('error', 'No pending setup email exists for this patient account.');

    // The setup routes are guest-only: drop the admin acting-as state so the
    // request below is truly unauthenticated (as the patient's browser would be).
    app('auth')->forgetGuards();

    // The emailed setup link itself refuses, with the single unified message.
    $token = app(PatientSetupTokenService::class)->create($account);

    $this->get(route('patient.setup.show', $token).'?email='.urlencode($account->email))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();
    Mail::assertSent(PatientAccountSetupMail::class, 1); // provisioning only

    expect($account->fresh()->trashed())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 10. UI affordances
|--------------------------------------------------------------------------
*/

it('offers Deactivate on live rows and Reactivate only on deactivated rows', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $active = pa2s2Activate(pa2s2Provision(
        pa2s2Patient('Row', 'Active', 'row.active@phase2s2.test'),
        'row.active.login@phase2s2.test'
    ));
    $pending = pa2s2Provision(
        pa2s2Patient('Row', 'Pending', 'row.pending@phase2s2.test'),
        'row.pending.login@phase2s2.test'
    );
    $deactivated = pa2s2Activate(pa2s2Provision(
        pa2s2Patient('Row', 'Gone', 'row.gone@phase2s2.test'),
        'row.gone.login@phase2s2.test'
    ));
    $deactivated->delete();

    $page = $this->actingAs($admin)->get(route('patient-accounts.index'));
    $page->assertOk();

    $html = $page->getContent();

    // Deactivate is offered for BOTH live statuses, with the danger variant.
    expect(substr_count($html, '/patient-accounts/'.$active->id.'/deactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$pending->id.'/deactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$deactivated->id.'/deactivate'))->toBe(0);

    // Reactivate is offered only for the deactivated row.
    expect(substr_count($html, '/patient-accounts/'.$deactivated->id.'/reactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$active->id.'/reactivate'))->toBe(0)
        ->and(substr_count($html, '/patient-accounts/'.$pending->id.'/reactivate'))->toBe(0);

    // The confirm modal carries the two-tone variant wiring.
    expect($html)
        ->toContain('data-action-variant="danger"')
        ->toContain('data-action-variant="success"')
        ->toContain("bg-[#B42318] hover:bg-[#912018]")
        ->toContain('accountActionIcon');

    // Sprint 1 cleanup: the label matches the underlying relationship,
    // which counts every ownership-evidence link (verified or not).
    expect($html)->toContain('Linked Pregnancies')
        ->and($html)->not->toContain('Verified Linked Pregnancies');
});

/*
|--------------------------------------------------------------------------
| 11. Summary counts track the lifecycle
|--------------------------------------------------------------------------
*/

it('moves the real summary counts with each lifecycle action', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $active = pa2s2Activate(pa2s2Provision(
        pa2s2Patient('Count', 'Active', 'count.active@phase2s2.test'),
        'count.active.login@phase2s2.test'
    ));
    $pending = pa2s2Provision(
        pa2s2Patient('Count', 'Pending', 'count.pending@phase2s2.test'),
        'count.pending.login@phase2s2.test'
    );

    $this->actingAs($admin)->get(route('patient-accounts.index'))
        ->assertSee('data-stat="total">2</p>', false)
        ->assertSee('data-stat="active">1</p>', false)
        ->assertSee('data-stat="pending">1</p>', false);

    $this->post(route('patient-accounts.deactivate', $active))->assertSessionHas('success');

    $this->get(route('patient-accounts.index'))
        ->assertSee('data-stat="total">2</p>', false)
        ->assertSee('data-stat="active">0</p>', false)
        ->assertSee('data-stat="pending">1</p>', false);

    $this->get(route('patient-accounts.index', ['status' => 'deactivated']))
        ->assertOk()
        ->assertSee('data-status="deactivated"', false);

    $this->post(route('patient-accounts.reactivate', $active))->assertSessionHas('success');

    $this->get(route('patient-accounts.index'))
        ->assertSee('data-stat="active">1</p>', false)
        ->assertSee('data-stat="pending">1</p>', false);
});

/*
|--------------------------------------------------------------------------
| 12. Pregnancy ownership + clinical records are never touched
|--------------------------------------------------------------------------
*/

it('never changes pregnancy ownership or clinical records across a lifecycle cycle', function () {
    Mail::fake();
    $admin = pa2s2Admin();

    $patient = pa2s2Patient('Ownership', 'Keeper', 'ownership.keeper@phase2s2.test');
    $account = pa2s2Activate(pa2s2Provision($patient, 'ownership.keeper.login@phase2s2.test'));

    $sibling = pa2s2Patient('Sibling', 'Episode', 'sibling.keeper@phase2s2.test');
    pa2s2Link($account, $sibling);

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => '2026-06-01',
        'gestational_age' => 20.0,
        'bp_sys' => 118,
        'bp_dia' => 76,
        'weight' => 60.5,
        'temperature' => 36.5,
        'fundic_height' => '20 cm',
        'fetal_heart_tone' => 'Regular 120-160',
        'fetal_movement' => 'Active',
        'next_visit_date' => '2026-06-15',
    ]);

    $history = MedicalHistory::create([
        'patient_id' => $patient->id,
        'epilepsy' => true,
        'diabetes' => false,
        'smoking' => false,
    ]);

    $before = [
        'users' => User::count(),
        'patients' => Patient::count(),
        'trashedUsers' => User::onlyTrashed()->count(),
        'links' => UserPregnancyLink::count(),
        'linkIds' => UserPregnancyLink::orderBy('id')->pluck('id')->all(),
        'visits' => PrenatalVisit::count(),
        'histories' => MedicalHistory::count(),
        'pointer' => $patient->fresh()->user_id,
        'password' => $account->fresh()->password,
        'flag' => (bool) $account->fresh()->must_change_password,
        'emailVerifiedAt' => $account->fresh()->email_verified_at,
    ];

    $this->actingAs($admin)->post(route('patient-accounts.deactivate', $account))->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $account))->assertSessionHas('success');

    $account->refresh();

    expect(User::count())->toBe($before['users'])
        ->and(Patient::count())->toBe($before['patients'])
        // Only deleted_at moved: never a hard delete.
        ->and(User::onlyTrashed()->count())->toBe($before['trashedUsers'])
        ->and(UserPregnancyLink::count())->toBe($before['links'])
        ->and(UserPregnancyLink::orderBy('id')->pluck('id')->all())->toBe($before['linkIds'])
        ->and(PrenatalVisit::count())->toBe($before['visits'])
        ->and(MedicalHistory::count())->toBe($before['histories'])
        ->and($patient->fresh()->user_id)->toBe($before['pointer'])
        ->and($sibling->fresh()->user_id)->toBeNull()
        ->and($account->password)->toBe($before['password'])
        ->and((bool) $account->must_change_password)->toBe($before['flag'])
        ->and($account->email_verified_at)->toEqual($before['emailVerifiedAt'])
        ->and($account->trashed())->toBeFalse();

    $visit->refresh();
    $history->refresh();
    expect($visit->bp_sys)->toBe(118)
        ->and($visit->bp_dia)->toBe(76)
        ->and($visit->gestational_age)->toEqual(20.0)
        ->and((bool) $history->epilepsy)->toBeTrue()
        ->and((bool) $history->diabetes)->toBeFalse();

    // Exactly one audit row per lifecycle step, nothing else written.
    expect(AuditLog::where('action', 'PATIENT_ACCOUNT_DEACTIVATED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'PATIENT_ACCOUNT_REACTIVATED')->count())->toBe(1)
        // The setup token from provisioning was purged by the revocation
        // cycle (deactivate + reactivate each clear password_reset_tokens),
        // so no token row survives.
        ->and(DB::table('password_reset_tokens')->count())->toBe(0);
});
