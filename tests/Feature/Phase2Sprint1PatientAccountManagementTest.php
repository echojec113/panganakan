<?php

use App\Mail\PatientAccountSetupMail;
use App\Models\AuditLog;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Models\UserPregnancyLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * DEPLA Phase 2 Sprint 1 — centralized Admin → Patient Account Management.
 *
 * Covers: admin-only authorization (route + middleware + controller +
 * FormRequest), the documented status doctrine, validated search, status
 * filters, 10-per-page server-side pagination with state preservation,
 * real summary counts, verified linked-pregnancy counts, a secret-free
 * details modal, Resend Setup Email reusing the Phase 1.5 endpoint, the
 * final account action matrix (the redundant Admin "Send Password Reset
 * Link" action was removed - patients reset their own passwords through the
 * patient-facing Forgot Password flow), Staff's preserved Patient Profile
 * permissions, and the guarantee that pregnancy ownership and clinical
 * records never change.
 *
 * All cases run against the isolated SQLite :memory: database with the
 * array mailer - no real email is ever sent.
 */

beforeEach(function () {
    $this->withoutVite();
});

function pa2s1Patient(string $first = 'Ana', string $last = 'Santos', string $email = 'patient@phase2s1.test'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 27,
        'address' => 'Phase 2 Sprint 1 test address',
        'contact_number' => '09170001111',
        'email' => $email,
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function pa2s1Admin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function pa2s1Staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/**
 * Provision through the REAL Phase 2B/1.5 endpoint so the account carries
 * its pointer, its verified pregnancy link and its pending-setup flag
 * exactly as production does.
 */
function pa2s1Provision(Patient $patient, string $loginEmail, ?User $actor = null): User
{
    $actor ??= pa2s1Staff();

    $response = test()->actingAs($actor)->post(
        route('patients.account.store', $patient->id),
        ['email' => $loginEmail]
    );

    $response->assertRedirect(route('patients.show', $patient->id));

    app('auth')->forgetGuards();

    return User::where('email', $loginEmail)->firstOrFail();
}

/** Simulate the patient completing the emailed setup (flag cleared, login usable). */
function pa2s1Activate(User $user): User
{
    $user->must_change_password = false;
    $user->save();

    return $user;
}

/** Explicit, ownership-evidence link row (never mass assigned). */
function pa2s1Link(User $user, Patient $patient): UserPregnancyLink
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
| 1-2. Access: guest / admin / sidebar visibility
|--------------------------------------------------------------------------
*/

it('redirects a guest to login from the account management route', function () {
    $this->get(route('patient-accounts.index'))->assertRedirect(route('login'));
});

it('renders the admin listing with its sidebar entry and keeps the entry off other roles', function () {
    $admin = pa2s1Admin();

    $this->actingAs($admin)
        ->get(route('patient-accounts.index'))
        ->assertOk()
        ->assertSee('Patient Account Management')
        ->assertSee('Manage Patient Portal access and account setup')
        ->assertSee('Total Accounts')
        ->assertSee('Active')
        ->assertSee('Pending Setup')
        ->assertSee('Deactivated')
        ->assertSee('Patient Name')
        ->assertSee('Email')
        ->assertSee('Account Created')
        ->assertSee('Linked Pregnancies')
        ->assertSee('Actions')
        // The redundant table heading/subtitle were removed by the table UI
        // refinement; the page header keeps the real title + subtitle above.
        ->assertDontSee('Patient Portal Accounts')
        ->assertDontSee('Account access only. No clinical information is shown on this page.')
        // Administration block + active navigation state.
        ->assertSee(route('patient-accounts.index'), false)
        ->assertSee('nav-item active', false);

    app('auth')->forgetGuards();

    // Staff: no sidebar entry (Administration is admin-only) and the module
    // itself is forbidden server-side.
    $staff = pa2s1Staff();
    $this->actingAs($staff)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Patient Accounts');

    $this->get(route('patient-accounts.index'))->assertForbidden();

    app('auth')->forgetGuards();

    // Patient portal never renders clinic navigation at all.
    $patientUser = User::factory()->create(['role' => 'patient']);
    $linked = pa2s1Patient('Portal', 'Holder', 'portal.holder@phase2s1.test');
    $linked->user_id = $patientUser->id;
    $linked->save();

    $this->actingAs($patientUser)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertDontSee('Patient Accounts')
        ->assertDontSee('Manage Staff');

    $this->get(route('patient-accounts.index'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 3. Authorization matrix
|--------------------------------------------------------------------------
*/

it('denies staff and patient users on the listing', function () {
    $target = pa2s1Activate(pa2s1Provision(pa2s1Patient('Auth', 'Matrix', 'auth.matrix@phase2s1.test'), 'auth.matrix.login@phase2s1.test'));

    foreach ([pa2s1Staff(), User::factory()->create(['role' => 'patient'])] as $intruder) {
        $this->actingAs($intruder)->get(route('patient-accounts.index'))->assertForbidden();
        $this->post(route('patient-accounts.deactivate', $target))->assertForbidden();
    }

    expect($target->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['PATIENT_ACCOUNT_DEACTIVATED', 'PATIENT_ACCOUNT_REACTIVATED'])->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 4. Status doctrine + real summary counts
|--------------------------------------------------------------------------
*/

it('calculates account statuses and real summary counts from the documented fields', function () {
    Mail::fake();
    $admin = pa2s1Admin();

    // Clinic roles are never patient accounts and must not be counted.
    pa2s1Staff();

    $active = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Active', 'Account', 'active.record@phase2s1.test'),
        'active.login@phase2s1.test'
    ));

    // Status must ignore email_verified_at entirely in both directions.
    $pending = pa2s1Provision(
        pa2s1Patient('Pending', 'Setup', 'pending.record@phase2s1.test'),
        'pending.login@phase2s1.test'
    );
    $pending->email_verified_at = now();
    $pending->save();

    $deactivated = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Deactivated', 'Account', 'deactivated.record@phase2s1.test'),
        'deactivated.login@phase2s1.test'
    ));
    $deactivated->delete();

    $response = $this->actingAs($admin)->get(route('patient-accounts.index'));

    $response->assertOk()
        ->assertSee('data-stat="total">3</p>', false)
        ->assertSee('data-stat="active">1</p>', false)
        ->assertSee('data-stat="pending">1</p>', false);

    $html = $response->getContent();

    expect(substr_count($html, 'data-status="active"'))->toBe(1)
        ->and(substr_count($html, 'data-status="pending-setup"'))->toBe(1)
        ->and(substr_count($html, 'data-status="deactivated"'))->toBe(1);

    // The active account keeps a NULL email_verified_at and is still Active.
    expect($active->fresh()->email_verified_at)->toBeNull()
        ->and((bool) $active->fresh()->must_change_password)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 5. Search
|--------------------------------------------------------------------------
*/

it('searches by patient name and by registered email with server-side validation', function () {
    Mail::fake();
    $admin = pa2s1Admin();

    pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Juan', 'Dela Cruz', 'juan.record@phase2s1.test'),
        'juan.delacruz@phase2s1.test'
    ));
    pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Maria', 'Clara', 'maria.record@phase2s1.test'),
        'maria.clara@phase2s1.test'
    ));

    // Legacy account whose users.name no longer mirrors the patient record:
    // the linked pregnancy's own name columns must still be searchable.
    $legacyUser = User::factory()->create([
        'role' => 'patient',
        'name' => 'Legacy Portal Name',
        'email' => 'legacy.portal@phase2s1.test',
    ]);
    $legacyPatient = pa2s1Patient('Bernadette', 'Reyes', 'bernadette.record@phase2s1.test');
    $legacyPatient->user_id = $legacyUser->id;
    $legacyPatient->save();

    $this->actingAs($admin)
        ->get(route('patient-accounts.index', ['search' => 'Juan']))
        ->assertOk()
        ->assertSee('juan.delacruz@phase2s1.test')
        ->assertDontSee('maria.clara@phase2s1.test');

    $this->get(route('patient-accounts.index', ['search' => 'maria.clara@']))
        ->assertOk()
        ->assertSee('maria.clara@phase2s1.test')
        ->assertDontSee('juan.delacruz@phase2s1.test');

    $this->get(route('patient-accounts.index', ['search' => 'Bernadette']))
        ->assertOk()
        ->assertSee('Legacy Portal Name');

    $this->get(route('patient-accounts.index', ['search' => 'no-such-account']))
        ->assertOk()
        ->assertSee('No patient accounts found.');

    // Validation: an over-long search term is rejected, not silently trimmed.
    $this->get(route('patient-accounts.index', ['search' => str_repeat('x', 101)]))
        ->assertSessionHasErrors('search');
});

/*
|--------------------------------------------------------------------------
| 6. Status filters + pagination
|--------------------------------------------------------------------------
*/

it('filters by status and paginates ten accounts per page with the controls preserved', function () {
    Mail::fake();
    $admin = pa2s1Admin();

    foreach (range(1, 12) as $index) {
        pa2s1Activate(pa2s1Provision(
            pa2s1Patient("Account{$index}", 'Holder', "holder{$index}@phase2s1.test"),
            "account{$index}@phase2s1.test"
        ));
    }

    foreach (range(1, 3) as $index) {
        pa2s1Provision(
            pa2s1Patient("Wait{$index}", 'Holder', "wait{$index}@phase2s1.test"),
            "waiting{$index}@phase2s1.test"
        );
    }

    foreach (range(1, 2) as $index) {
        $account = pa2s1Activate(pa2s1Provision(
            pa2s1Patient("Gone{$index}", 'Holder', "gone{$index}@phase2s1.test"),
            "gone{$index}@phase2s1.test"
        ));
        $account->delete();
    }

    $all = $this->actingAs($admin)->get(route('patient-accounts.index'));
    expect(substr_count($all->getContent(), 'data-status="'))->toBe(10);

    $pageTwo = $this->get(route('patient-accounts.index', ['page' => 2]));
    expect(substr_count($pageTwo->getContent(), 'data-status="'))->toBe(7);

    $active = $this->get(route('patient-accounts.index', ['status' => 'active']));
    expect(substr_count($active->getContent(), 'data-status="active"'))->toBe(10);

    $activePageTwo = $this->get(route('patient-accounts.index', ['status' => 'active', 'page' => 2]));
    expect(substr_count($activePageTwo->getContent(), 'data-status="active"'))->toBe(2);

    $pending = $this->get(route('patient-accounts.index', ['status' => 'pending']));
    expect(substr_count($pending->getContent(), 'data-status="pending-setup"'))->toBe(3);

    $deactivated = $this->get(route('patient-accounts.index', ['status' => 'deactivated']));
    expect(substr_count($deactivated->getContent(), 'data-status="deactivated"'))->toBe(2);

    // Search term and status filter survive into the page-2 link, and page 2
    // itself keeps the term in the search field (query string carried over).
    $filteredPageOne = $this->get(route('patient-accounts.index', [
        'status' => 'active',
        'search' => 'Holder',
    ]));

    expect(substr_count($filteredPageOne->getContent(), 'data-status="active"'))->toBe(10);

    preg_match('/href="([^"]*page=2[^"]*)"/', $filteredPageOne->getContent(), $matches);
    $pageTwoUrl = html_entity_decode($matches[1] ?? '');

    expect($pageTwoUrl)
        ->toContain('search=Holder')
        ->toContain('status=active');

    $preserved = $this->get(route('patient-accounts.index', [
        'status' => 'active',
        'search' => 'Holder',
        'page' => 2,
    ]));

    $preserved->assertOk()->assertSee('value="Holder"', false);
    expect(substr_count($preserved->getContent(), 'data-status="active"'))->toBe(2);

    // An unknown status value is a validation error, never a silent fallback.
    $this->get(route('patient-accounts.index', ['status' => 'bogus']))
        ->assertSessionHasErrors('status');
});

/*
|--------------------------------------------------------------------------
| 7-8. Linked pregnancy counts + secret-free details modal
|--------------------------------------------------------------------------
*/

it('shows verified linked pregnancy counts without exposing secrets or clinical data', function () {
    Mail::fake();
    $admin = pa2s1Admin();

    $patient = pa2s1Patient('Linked', 'Mother', 'linked.mother@phase2s1.test');
    $account = pa2s1Activate(pa2s1Provision($patient, 'linked.mother.login@phase2s1.test'));

    pa2s1Link($account, pa2s1Patient('First', 'Sibling', 'first.sibling@phase2s1.test'));
    pa2s1Link($account, pa2s1Patient('Second', 'Sibling', 'second.sibling@phase2s1.test'));

    $response = $this->actingAs($admin)->get(route('patient-accounts.index'));

    $response->assertOk()
        ->assertSee('data-links="3"', false)
        ->assertSee('data-details-links="3"', false)
        // Details modal payload: identity, status, setup, creation date, links.
        ->assertSee('Account Details', false)
        // Label says "Linked Pregnancies" (the count includes every
        // ownership-evidence link row; Sprint 2 corrected the wording).
        ->assertSee('Linked Pregnancies', false)
        ->assertSee('data-details-name="Linked Mother"', false)
        ->assertSee('data-details-email="linked.mother.login@phase2s1.test"', false)
        ->assertSee('data-details-status="Active"', false)
        ->assertSee('data-details-setup="Completed - patient chose their own password"', false)
        // Never secrets, tokens or clinical values.
        ->assertDontSee($account->password, false)
        ->assertDontSee('type="password"', false)
        ->assertDontSee('/reset-password/', false)
        ->assertDontSee('remember_token')
        ->assertDontSee($patient->contact_number, false)
        ->assertDontSee($patient->address, false);
});

/*
|--------------------------------------------------------------------------
| 9. Resend Setup Email (reuses the Phase 1.5 endpoint)
|--------------------------------------------------------------------------
*/

it('lets an admin resend the setup email for a pending account through the existing endpoint', function () {
    Mail::fake();
    $admin = pa2s1Admin();

    $patient = pa2s1Patient('Awaiting', 'Setup', 'awaiting.record@phase2s1.test');
    $pending = pa2s1Provision($patient, 'awaiting.setup@phase2s1.test');

    $page = $this->actingAs($admin)->get(route('patient-accounts.index'));

    $page->assertOk()
        ->assertSee('Resend Setup Email', false)
        ->assertSee('data-action-url="'.route('patients.account.resend-setup-email', $patient->id).'"', false)
        ->assertSee('data-status="pending-setup"', false);

    // Exactly one setup token system: the admin button posts to the Phase 1.5
    // Patient Profile endpoint (24-hour single-use token, 60s throttle, the
    // existing PATIENT_SETUP_EMAIL_RESENT audit - all unchanged).
    $this->travel(61)->seconds();

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    Mail::assertSent(PatientAccountSetupMail::class, 2); // provisioning + resend

    expect(AuditLog::where('action', 'PATIENT_SETUP_EMAIL_RESENT')->count())->toBe(1)
        ->and($pending->fresh()->must_change_password)->toBeTrue()
        ->and(User::where('role', 'patient')->count())->toBe(1)
        ->and(UserPregnancyLink::count())->toBe(1);

    // Every token row in the shared table is still a namespaced setup key:
    // no second, un-namespaced token system was introduced.
    $tokenKeys = DB::table('password_reset_tokens')->pluck('email')->all();
    expect($tokenKeys)->not->toBeEmpty();
    foreach ($tokenKeys as $key) {
        expect(str_starts_with((string) $key, 'setup:'))->toBeTrue();
    }

    // An ACTIVE account is never offered the setup resend.
    $this->get(route('patient-accounts.index', ['status' => 'active']))
        ->assertDontSee(route('patients.account.resend-setup-email', $patient->id), false);
});

/*
|--------------------------------------------------------------------------
| 10. Removed Admin reset-link endpoint + final action matrix
|--------------------------------------------------------------------------
*/

it('no longer exposes the admin reset-link route', function () {
    // The named route is gone entirely, so no view, controller or test can
    // accidentally rebuild a link to it.
    expect(Route::has('patient-accounts.send-reset-link'))->toBeFalse();

    // A direct POST to the old path is a plain 404 (not a 403 oracle, not a
    // redirect) for every role, because no route matches it any more.
    $admin = pa2s1Admin();
    $target = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Gone', 'Route', 'gone.route@phase2s1.test'),
        'gone.route.login@phase2s1.test'
    ));

    $this->actingAs($admin)
        ->post('/patient-accounts/'.$target->id.'/reset-link')
        ->assertNotFound();

    app('auth')->forgetGuards();

    $this->post('/patient-accounts/'.$target->id.'/reset-link')->assertNotFound();

    expect($target->fresh()->trashed())->toBeFalse();
});

it('offers exactly the final action matrix per account status', function () {
    Mail::fake();
    Notification::fake();
    $admin = pa2s1Admin();

    $active = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Matrix', 'Active', 'matrix.active@phase2s1.test'),
        'matrix.active.login@phase2s1.test'
    ));
    $pending = pa2s1Provision(
        pa2s1Patient('Matrix', 'Pending', 'matrix.pending@phase2s1.test'),
        'matrix.pending.login@phase2s1.test'
    );
    $deactivated = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Matrix', 'Gone', 'matrix.gone@phase2s1.test'),
        'matrix.gone.login@phase2s1.test'
    ));
    $deactivated->delete();

    $html = $this->actingAs($admin)->get(route('patient-accounts.index'))->assertOk()->getContent();

    // View Details is offered on every row (one button per row; the JS
    // function definition itself is excluded by matching the onclick usage).
    expect(substr_count($html, 'onclick="openAccountDetails(this)"'))->toBe(3);

    // Active: View Details + Deactivate only (no Resend Setup, no reset link).
    expect(substr_count($html, '/patient-accounts/'.$active->id.'/deactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$active->id.'/reactivate'))->toBe(0)
        ->and($html)->not->toContain(route('patients.account.resend-setup-email', $active->patientAccount->id));

    // Pending Setup: View Details + Resend Setup Email + Deactivate only.
    expect(substr_count($html, '/patient-accounts/'.$pending->id.'/deactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$pending->id.'/reactivate'))->toBe(0)
        ->and(substr_count($html, route('patients.account.resend-setup-email', $pending->patientAccount->id)))->toBe(1);

    // Deactivated: View Details + Reactivate only (no Deactivate, no Resend).
    expect(substr_count($html, '/patient-accounts/'.$deactivated->id.'/reactivate'))->toBe(1)
        ->and(substr_count($html, '/patient-accounts/'.$deactivated->id.'/deactivate'))->toBe(0)
        ->and($html)->not->toContain(route('patients.account.resend-setup-email', $deactivated->patientAccount->id));

    // The removed action is offered nowhere on the page.
    expect($html)->not->toContain('patient-accounts.send-reset-link')
        ->and($html)->not->toContain('Send Password Reset Link')
        ->and($html)->not->toContain('Send Reset Link');
});

it('still lets a patient reset their own password through the forgot-password flow', function () {
    Notification::fake();

    $account = pa2s1Activate(pa2s1Provision(
        pa2s1Patient('Forgot', 'Works', 'forgot.works@phase2s1.test'),
        'forgot.works.login@phase2s1.test'
    ));

    // The patient-facing flow is untouched: POST /forgot-password sends the
    // ordinary Laravel reset notification to the REGISTERED email.
    $this->post(route('password.email'), ['email' => $account->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($account, \Illuminate\Auth\Notifications\ResetPassword::class);
    expect(DB::table('password_reset_tokens')->where('email', $account->email)->exists())->toBeTrue();

    // A crafted body must never retarget the mail to another address.
    $attacker = User::factory()->create(['role' => 'patient', 'email' => 'forgot.attacker@phase2s1.test']);
    $this->post(route('password.email'), ['email' => $attacker->email])->assertSessionHas('status');
    Notification::assertSentTo($attacker, \Illuminate\Auth\Notifications\ResetPassword::class);

    // The reset token is real and consumable: the patient can set a new
    // password with it (proving the flow end to end, without any email).
    $token = \Illuminate\Support\Facades\Password::getRepository()->create($account);
    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $account->email,
        'password' => 'brand-new-forgot-password',
        'password_confirmation' => 'brand-new-forgot-password',
    ])->assertSessionHasNoErrors();

    expect(\Illuminate\Support\Facades\Hash::check('brand-new-forgot-password', $account->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 11. Pending + Deactivated eligibility
|--------------------------------------------------------------------------
*/


it('refuses every account-management action for a deactivated account', function () {
    Notification::fake();
    Mail::fake();
    $admin = pa2s1Admin();

    $patient = pa2s1Patient('Archived', 'Portal', 'archived.portal@phase2s1.test');
    $account = pa2s1Activate(pa2s1Provision($patient, 'archived.login@phase2s1.test'));
    $account->delete();

    $page = $this->actingAs($admin)->get(route('patient-accounts.index'));

    $page->assertOk()
        ->assertSee('data-status="deactivated"', false)
        // Neither lifecycle action is offered for a deactivated row.
        ->assertDontSee(route('patients.account.resend-setup-email', $patient->id), false)
        ->assertDontSee('/patient-accounts/'.$account->id.'/deactivate', false);

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('error', 'No pending setup email exists for this patient account.');

    Notification::assertNothingSent();
    Mail::assertSent(PatientAccountSetupMail::class, 1); // provisioning only

    expect($account->fresh()->trashed())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 12. Staff permissions preserved + Admin module denied
|--------------------------------------------------------------------------
*/

it('keeps staff patient-profile account permissions while denying the admin module', function () {
    Mail::fake();
    Notification::fake();
    $staff = pa2s1Staff();

    $patient = pa2s1Patient('Staff', 'Created', 'staff.created@phase2s1.test');

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), ['email' => 'staff.created.login@phase2s1.test'])
        ->assertRedirect(route('patients.show', $patient->id));

    $created = User::where('email', 'staff.created.login@phase2s1.test')->firstOrFail();
    expect((bool) $created->must_change_password)->toBeTrue();

    $this->travel(61)->seconds();

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    Mail::assertSent(PatientAccountSetupMail::class, 2); // create + resend

    // The centralized Admin module stays closed to Staff.
    $this->get(route('patient-accounts.index'))->assertForbidden();
    $this->post(route('patient-accounts.deactivate', $created))->assertForbidden();

    Notification::assertNothingSent();
    expect($created->fresh()->trashed())->toBeFalse()
        ->and(AuditLog::whereIn('action', ['PATIENT_ACCOUNT_DEACTIVATED', 'PATIENT_ACCOUNT_REACTIVATED'])->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 13. Pregnancy ownership + clinical records are never touched
|--------------------------------------------------------------------------
*/

it('never changes pregnancy ownership or clinical records during account management', function () {
    Mail::fake();
    Notification::fake();
    $admin = pa2s1Admin();

    $patient = pa2s1Patient('Ownership', 'Keeper', 'ownership.keeper@phase2s1.test');
    $active = pa2s1Activate(pa2s1Provision($patient, 'ownership.login@phase2s1.test'));

    $pendingPatient = pa2s1Patient('Pending', 'Keeper', 'pending.keeper@phase2s1.test');
    $pending = pa2s1Provision($pendingPatient, 'pending.keeper.login@phase2s1.test');

    $sibling = pa2s1Patient('Sibling', 'Episode', 'sibling.keeper@phase2s1.test');
    pa2s1Link($active, $sibling);

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
        'trashedPatients' => Patient::onlyTrashed()->count(),
        'links' => UserPregnancyLink::count(),
        'linkIds' => UserPregnancyLink::orderBy('id')->pluck('id')->all(),
        'visits' => PrenatalVisit::count(),
        'histories' => MedicalHistory::count(),
        'pointer' => $patient->fresh()->user_id,
        'activePassword' => $active->fresh()->password,
        'pendingFlag' => (bool) $pending->fresh()->must_change_password,
    ];

    // Exercise every Admin surface added by this sprint.
    $this->actingAs($admin)->get(route('patient-accounts.index'))->assertOk();
    $this->get(route('patient-accounts.index', ['search' => 'Keeper', 'status' => 'active']))->assertOk();
    $this->get(route('patient-accounts.index', ['status' => 'pending']))->assertOk();
    $this->get(route('patient-accounts.index', ['status' => 'deactivated']))->assertOk();

    // Exercise the lifecycle surface (the only remaining write action).
    $this->post(route('patient-accounts.deactivate', $active))
        ->assertSessionHas('success');
    $this->post(route('patient-accounts.reactivate', $active))
        ->assertSessionHas('success');

    $this->travel(61)->seconds();

    $this->post(route('patients.account.resend-setup-email', $pendingPatient->id))
        ->assertSessionHas('success');

    expect(User::count())->toBe($before['users'])
        ->and(Patient::count())->toBe($before['patients'])
        ->and(Patient::onlyTrashed()->count())->toBe($before['trashedPatients'])
        ->and(UserPregnancyLink::count())->toBe($before['links'])
        ->and(UserPregnancyLink::orderBy('id')->pluck('id')->all())->toBe($before['linkIds'])
        ->and(PrenatalVisit::count())->toBe($before['visits'])
        ->and(MedicalHistory::count())->toBe($before['histories'])
        ->and($patient->fresh()->user_id)->toBe($before['pointer'])
        ->and($sibling->fresh()->user_id)->toBeNull()
        ->and($active->fresh()->password)->toBe($before['activePassword'])
        ->and((bool) $active->fresh()->must_change_password)->toBeFalse()
        ->and($active->fresh()->deleted_at)->toBeNull()
        ->and((bool) $pending->fresh()->must_change_password)->toBe($before['pendingFlag'])
        ->and($pending->fresh()->deleted_at)->toBeNull();

    $visit->refresh();
    $history->refresh();
    expect($visit->bp_sys)->toBe(118)
        ->and($visit->bp_dia)->toBe(76)
        ->and($visit->gestational_age)->toEqual(20.0)
        ->and((bool) $history->epilepsy)->toBeTrue()
        ->and((bool) $history->diabetes)->toBeFalse();
});
