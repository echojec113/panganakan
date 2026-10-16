<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * DEPLA Phase 2D — Admin/Staff Patient Account Creation UI.
 *
 * UI integration only: the section and modal render on the existing Patient
 * Profile and POST to the existing Phase 2B endpoint (patients.account.store).
 * No new backend endpoint, no invitation link, no password viewing/reset.
 * Everything runs against isolated SQLite :memory:.
 */
beforeEach(function () {
    $this->withoutVite();
});

function phase2dPatient(string $first = 'Portal', string $last = 'Ui', ?string $email = 'clinical.record@phase2d.test'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 27,
        'address' => 'Phase 2D test address',
        'contact_number' => '09171234567',
        'email' => $email,
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase2dActor(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase2dPortalUser(string $password = 'Initial-Passw0rd!', array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'role' => 'patient',
        'password' => Hash::make($password),
    ], $overrides));
}

function phase2dLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

function phase2dPayload(array $overrides = []): array
{
    // Phase 1.5: the login email is the ONLY credential input.
    return array_merge([
        'email' => 'portal.login@phase2d.test',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Account section visibility and states
|--------------------------------------------------------------------------
*/

it('shows the Patient Login Account section to an admin', function () {
    $admin = phase2dActor('admin');
    $patient = phase2dPatient();

    $response = $this->actingAs($admin)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Patient Login Account');
    $response->assertSeeText('No Account');
    $response->assertSee('Create Patient Account');
});

it('shows the Patient Login Account section to a staff member', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Patient Login Account');
    $response->assertSeeText('No Account');
    $response->assertSee('Create Patient Account');
});

it('offers account creation for an active patient with no login account, even without a clinical email', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient('No', 'Email', null);

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('This patient does not yet have portal login credentials');
    $response->assertSee('Create Patient Account');
    $response->assertSee('id="openCreateAccountModal"', false);
});

it('hides the create action and shows the active account state when a linked account exists', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $user = phase2dPortalUser();
    phase2dLink($patient, $user);

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Active Account');
    $response->assertSee($user->email);
    $response->assertDontSee('Create Patient Account');
    $response->assertDontSee('id="openCreateAccountModal"', false);
});

it('shows the archived account state and hides the create action for a linked soft-deleted account', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $user = phase2dPortalUser();
    phase2dLink($patient, $user);
    $user->delete();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Archived Account');
    $response->assertSee($user->email);
    $response->assertSee('archived and cannot currently sign in');
    $response->assertDontSee('Create Patient Account');
    $response->assertDontSee('id="openCreateAccountModal"', false);
});

it('renders no account UI for an archived patient record and refuses provisioning', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $patient->delete();

    // The profile itself does not render archived records (existing behavior).
    $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertNotFound();

    // The backend still refuses provisioning for the archived record.
    $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHasErrors('status');

    expect(User::where('role', 'patient')->count())->toBe(0);
    expect(Patient::onlyTrashed()->count())->toBe(1);
});

it('displays the linked account login email', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $user = phase2dPortalUser('Initial-Passw0rd!', ['email' => 'portal.login@phase2d.test']);
    phase2dLink($patient, $user);

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Login Email');
    $response->assertSee('portal.login@phase2d.test');
});

it('shows Password Change Required when the flag is set and Password Updated when it is cleared', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $user = phase2dPortalUser();
    $user->forceFill(['must_change_password' => true])->save();
    phase2dLink($patient, $user);

    $flagged = $this->actingAs($staff)->get(route('patients.show', $patient->id));
    $flagged->assertOk();
    $flagged->assertSeeText('Password Change Required');
    $flagged->assertDontSeeText('Password Updated');

    $user->forceFill(['must_change_password' => false])->save();

    $updated = $this->actingAs($staff)->get(route('patients.show', $patient->id));
    $updated->assertOk();
    $updated->assertSeeText('Password Updated');
    $updated->assertDontSeeText('Password Change Required');
});

it('never renders the password or its hash in the Patient Profile', function () {
    $plain = 'S3cret-Phase2D-Initial!';
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $user = phase2dPortalUser($plain);
    phase2dLink($patient, $user);

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertDontSee($plain);
    $response->assertDontSee($user->password);
    $response->assertDontSee('$2y$', false);
    $response->assertDontSee('must_change_password');
});

/*
|--------------------------------------------------------------------------
| Modal markup, submission target and safety
|--------------------------------------------------------------------------
*/

it('points the modal form at the existing Phase 2B endpoint', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('action="' . route('patients.account.store', $patient->id) . '"', false);
    $response->assertSee('method="POST"', false);
});

it('includes CSRF protection in the modal form', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('name="_token"', false);
});

it('renders the email-only modal, prefills the clinical email and starts closed', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('name="email"', false);
    $response->assertSee('type="email"', false);
    $response->assertSee('autocomplete="email"', false);
    $response->assertSee('Login Email');

    // Phase 1.5: no password inputs exist in the modal any more.
    $response->assertDontSee('name="password"', false);
    $response->assertDontSee('name="password_confirmation"', false);
    $response->assertDontSee('Initial Password');
    $response->assertDontSee('Confirm Initial Password');

    // The mandated setup-email explanation is visible to staff.
    $response->assertSee("An account setup email will be sent to the patient's email address.", false);
    $response->assertSee('valid for 24 hours');

    // Prefill from the clinical record (editable, never written back).
    $response->assertSee('value="clinical.record@phase2d.test"', false);

    // Closed by default; data-open drives the validation-failure reopen.
    $response->assertSee('data-open="false"', false);
});

it('displays validation errors safely and reopens the modal', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $post = $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload([
            'email' => 'not-an-email-address',
        ]));

    $post->assertRedirect(route('patients.show', $patient->id));
    $post->assertSessionHasErrors('email');

    $profile = $this->get(route('patients.show', $patient->id));

    $profile->assertOk();
    $profile->assertSee('Please review the highlighted issues.');
    $profile->assertSee('must be a valid email address');

    // Modal reopened for correction, entered email preserved.
    $profile->assertSee('data-open="true"', false);
    $profile->assertSee('value="not-an-email-address"', false);

    expect(Patient::find($patient->id)->user_id)->toBeNull();
    expect(User::where('role', 'patient')->count())->toBe(0);
});

it('never renders or stores password inputs, and ignores posted password fields', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    // A crafted POST with password fields still succeeds; the values are
    // never validated, stored or flashed (Phase 1.5).
    $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload([
            'password' => 'Temporary-Phase2D!',
            'password_confirmation' => 'Different-Phase2D!',
        ]))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHasNoErrors();

    expect(session()->getOldInput('password'))->toBeNull();
    expect(session()->getOldInput('password_confirmation'))->toBeNull();

    $created = User::where('email', 'portal.login@phase2d.test')->first();

    expect($created)->not->toBeNull();
    expect(Hash::check('Temporary-Phase2D!', $created->password))->toBeFalse();

    $profile = $this->get(route('patients.show', $patient->id));

    $profile->assertOk();
    $profile->assertDontSee('Temporary-Phase2D!', false);
    $profile->assertDontSee('name="password"', false);
    $profile->assertDontSee('name="password_confirmation"', false);
});

it('redirects a successful creation back to the Patient Profile without exposing the password', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload());

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('success');
    expect(session('success'))->not->toContain('Initial-Passw0rd!');
});

it('refreshes the displayed account state after a successful creation', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertRedirect(route('patients.show', $patient->id));

    $profile = $this->get(route('patients.show', $patient->id));

    $profile->assertOk();
    $profile->assertSeeText('Active Account');
    $profile->assertSeeText('Password Change Required');
    $profile->assertSee('portal.login@phase2d.test');
    $profile->assertDontSee('Create Patient Account');
});

it('keeps the backend authoritative when a stale modal submits for an already-linked patient', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();
    $existing = phase2dPortalUser('Initial-Passw0rd!', ['email' => 'already.linked@phase2d.test']);
    phase2dLink($patient, $existing);

    $this->actingAs($staff)
        ->from(route('patients.show', $patient->id))
        ->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertSessionHasErrors('status');

    expect(User::where('role', 'patient')->count())->toBe(1);
    expect($patient->fresh()->user_id)->toBe($existing->id);
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('rejects a guest account-creation attempt', function () {
    $patient = phase2dPatient();

    $this->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertRedirect(route('login'));

    expect(User::where('role', 'patient')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects a patient-role account-creation attempt', function () {
    $patient = phase2dPatient();
    $patientUser = phase2dActor('patient');

    $this->actingAs($patientUser)
        ->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertForbidden();

    expect(User::where('role', 'patient')->count())->toBe(1);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects an unknown-role account-creation attempt', function () {
    $patient = phase2dPatient();
    $unknown = phase2dActor('clinic_observer');

    $this->actingAs($unknown)
        ->post(route('patients.account.store', $patient->id), phase2dPayload())
        ->assertForbidden();

    expect($patient->fresh()->user_id)->toBeNull();
});

it('keeps the existing clinic authorization boundaries on the Patient Profile', function () {
    $patient = phase2dPatient();

    $this->get(route('patients.show', $patient->id))->assertRedirect(route('login'));
    $this->actingAs(phase2dActor('patient'))->get(route('patients.show', $patient->id))->assertForbidden();
    $this->actingAs(phase2dActor('clinic_observer'))->get(route('patients.show', $patient->id))->assertForbidden();
    $this->actingAs(phase2dActor('admin'))->get(route('patients.show', $patient->id))->assertOk();
    $this->actingAs(phase2dActor('staff'))->get(route('patients.show', $patient->id))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Non-regression of the surrounding profile and forms
|--------------------------------------------------------------------------
*/

it('keeps the existing clinical sections of the Patient Profile rendering', function () {
    $staff = phase2dActor('staff');
    $patient = phase2dPatient();

    $response = $this->actingAs($staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('patient-profile-nav', false);
    $response->assertSee('Pregnancy at a Glance');
    $response->assertSee('Assigned Staff');
    $response->assertSee('Medical History');
    $response->assertSee('Risk Assessment');
    $response->assertSee('Overview');
});

it('leaves the Add New Patient form unchanged', function () {
    $staff = phase2dActor('staff');

    $response = $this->actingAs($staff)->get(route('patients.create'));

    $response->assertOk();
    $response->assertSee('name="first_name"', false);
    $response->assertSee('name="last_name"', false);
    $response->assertSee('name="birthdate"', false);
    $response->assertSee('name="lmp"', false);
    $response->assertSee('name="gravida"', false);

    // Phase 2D content never leaks into the create form.
    $response->assertDontSee('Patient Login Account');
    $response->assertDontSee('Create Patient Account');
});

/*
|--------------------------------------------------------------------------
| Phase 2D §12 — friendly portal eligibility message (status stays 403)
|--------------------------------------------------------------------------
*/

it('answers a friendly 403 for unlinked or archived-linked portal logins', function () {
    // Unlinked patient-role login.
    $unlinked = phase2dPortalUser();
    $this->actingAs($unlinked)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    // Login whose patient record has been archived.
    $patient = phase2dPatient();
    $linked = phase2dPortalUser();
    phase2dLink($patient, $linked);
    $patient->delete();

    $this->actingAs($linked)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    // The message itself discloses nothing about the account internals.
    $this->actingAs($unlinked)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Please contact the clinic for assistance.');
    expect(User::where('role', 'patient')->count())->toBe(2);
});
