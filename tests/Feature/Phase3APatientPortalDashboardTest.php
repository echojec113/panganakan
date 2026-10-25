<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\SessionGuard;

/*
|--------------------------------------------------------------------------
| Phase 3A — Patient Portal Dashboard + My Profile
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the dashboard, the
| read-only My Profile page, identity isolation (no request-supplied ID),
| the full authorization matrix on BOTH portal routes, and regression
| probes for Phases 2B/2C/2D, clinic-route protection and logout.
*/

function phase3aUser(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase3aPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 28,
        'email' => 'maria.contact@phase3a.test',
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3aLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3aEligible(): array
{
    $user = phase3aUser('patient');
    $patient = phase3aPatient();
    phase3aLink($patient, $user);

    return [$user, $patient];
}

function phase3aMissingOptionalPatient(): Patient
{
    return phase3aPatient([
        'middle_name' => null,
        'email' => null,
        'contact_number' => null,
        'address' => null,
        'address_line' => null,
        'barangay' => null,
        'city_municipality' => null,
    ]);
}

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

it('shows the dashboard to an eligible linked patient', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk()
        ->assertSee('Patient Portal')
        ->assertSee('Welcome to your DEPLA Family Care patient portal.')
        ->assertSee('DEPLA Family Care');
});

it('shows the other-services announcement with the exact secure Facebook link', function () {
    [$user] = phase3aEligible();

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Other Services and Announcements')
        ->assertSee('hearing services, counseling, tuli (circumcision)')
        ->assertSee('Check the page for current announcements and availability.')
        ->assertSee('href="https://www.facebook.com/share/1CSM8f54dE/"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('View Our Facebook Page');
});

it('greets with the linked patient first name and not the middle or full name', function () {
    [$user, $patient] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk()
        ->assertSee('Welcome, ' . $patient->first_name . '!')
        ->assertDontSee('Welcome, Maria Cruz!')
        ->assertDontSee('Welcome, Maria Cruz')
        ->assertDontSee('Welcome, ' . $patient->first_name . ' ' . $patient->middle_name);
});

it('links the My Profile card to the read-only profile route', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk()
        ->assertSee('href="' . route('patient.profile.show') . '"', false)
        ->assertSee('My Profile')
        ->assertSee('View your personal information.');
});

it('links the Appointments card to the My Appointments page (Phase 3B.3 replaced the placeholder)', function () {
    [$user] = phase3aEligible();

    $html = $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('href="' . route('patient.appointments.index') . '"', false)
        ->assertSee('My Appointments')
        ->assertSee('Request checkup appointments online and track their status.')
        // The dashboard card itself never hosts a form (requests live on
        // the dedicated create page); the only form here is still logout.
        ->assertDontSee('appointment-form')
        ->assertDontSee('action="' . url('/appointments') . '"')
        ->getContent();

    // The only form on the dashboard is the layout's POST logout.
    expect(substr_count($html, '<form'))->toBe(1);
});

it('links Medical Records to the Phase 3E.2 read-only records page', function () {
    [$user] = phase3aEligible();

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('href="' . route('patient.medical-records.index') . '"', false)
        ->assertSee('Medical Records')
        ->assertSee('View the records the clinic keeps for your current pregnancy.')
        ->assertSee('Open My Medical Records')
        // Phase 3E.2 replaced the placeholder: no coming-soon copy remains.
        ->assertDontSee('Medical record access will be available soon.')
        ->assertDontSee('Coming Soon')
        ->assertDontSee('medical records are coming soon');
});

it('shows Account Security as informational only with no authentication metadata', function () {
    [$user] = phase3aEligible();

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Account Security')
        ->assertSee('Your account is protected.')
        ->assertDontSee('must_change_password')
        ->assertDontSee('remember_token')
        ->assertDontSee('$2y$');
});

it('renders logout as a POST form with CSRF and no GET logout link', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk()
        ->assertSee('name="_token"', false);

    expect($response->getContent())->toMatch(
        '/<form method="POST" action="' . preg_quote(route('logout'), '/') . '"/'
    );
});

it('contains no clinical records and no clinic-staff navigation', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk();

    foreach ([
        'HIGH RISK', 'Gestational', 'Prenatal', 'Ultrasound', 'Birth Plan',
        'Referral', 'Medical History', 'sidebar-nav', 'Manage Staff',
        'Audit Logs', 'Risk Monitoring', 'Pregnancy Outcome Monitoring',
        'Total Patients',
    ] as $forbidden) {
        $response->assertDontSee($forbidden);
    }
});

/*
|--------------------------------------------------------------------------
| My Profile (read-only)
|--------------------------------------------------------------------------
*/

it('lets an eligible patient view their own read-only profile', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertSee('My Profile')
        ->assertSee('read-only')
        ->assertSee('Back to Dashboard');
});

it('displays the linked patient basic details', function () {
    [$user, $patient] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertSee('First Name')
        ->assertSee($patient->first_name)
        ->assertSee($patient->middle_name)
        ->assertSee($patient->last_name)
        ->assertSee($patient->contact_number);
});

it('shows Not provided for missing optional values', function () {
    $user = phase3aUser('patient');
    $patient = phase3aMissingOptionalPatient();
    phase3aLink($patient, $user);

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertSee('Not provided')
        ->assertSee('Middle Name')
        ->assertSee('Contact Number')
        ->assertSee('Address');

    expect(substr_count($response->getContent(), 'Not provided'))->toBeGreaterThanOrEqual(4);
});

it('distinguishes the patient contact email from the login email', function () {
    $user = phase3aUser('patient');
    $patient = phase3aPatient(['email' => 'clinical.contact@phase3a.test']);
    phase3aLink($patient, $user);

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertSee('Patient Contact Email')
        ->assertSee('Login Email')
        ->assertSee('clinical.contact@phase3a.test')
        ->assertSee($user->email);

    // Neither value may silently replace the other.
    expect($user->email)->not->toBe('clinical.contact@phase3a.test');
});

it('hides the separate login email row when both emails are identical', function () {
    $user = phase3aUser('patient');
    $patient = phase3aPatient(['email' => $user->email]);
    phase3aLink($patient, $user);

    $this->actingAs($user)->get(route('patient.profile.show'))
        ->assertOk()
        ->assertSee('Patient Contact Email')
        ->assertDontSee('Login Email');
});

it('formats the address with the existing structured address accessor', function () {
    [$user, $patient] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertSee($patient->address_line)
        ->assertSee('Barangay ' . $patient->barangay)
        ->assertSee($patient->city_municipality)
        ->assertDontSee('Not provided');
});

it('presents no edit, update or delete capability', function () {
    [$user] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertDontSee('Edit Profile')
        ->assertDontSee('Update Personal Information')
        ->assertDontSee('Change Email')
        ->assertDontSee('Change Password')
        ->assertDontSee('Delete Account')
        ->assertDontSee('Upload Profile Picture')
        ->assertDontSee('method="PUT"')
        ->assertDontSee('method="PATCH"')
        ->assertDontSee('method="DELETE"');

    // The only form action anywhere near the page is logout.
    expect(substr_count($response->getContent(), '<form'))->toBe(1)
        ->and($response->getContent())->toContain('action="' . route('logout') . '"')
        ->and($response->getContent())->not->toContain('action="' . route('patient.profile.show') . '"');
});

it('renders no internal identifiers and no password material', function () {
    [$user, $patient] = phase3aEligible();

    $response = $this->actingAs($user)->get(route('patient.profile.show'));

    $response->assertOk()
        ->assertDontSee('user_id')
        ->assertDontSee('patient_id')
        ->assertDontSee('/patients/' . $patient->id)
        ->assertDontSee($user->password)
        ->assertDontSee('$2y$')
        ->assertDontSee('$1$');
});

/*
|--------------------------------------------------------------------------
| Authorization — direct URL access on BOTH routes
|--------------------------------------------------------------------------
*/

it('redirects a guest to login on both portal pages', function () {
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));
    $this->get(route('patient.profile.show'))->assertRedirect(route('login'));
});

it('keeps admin away from the patient-only profile but redirects the dashboard', function () {
    $admin = phase3aUser('admin');

    $this->actingAs($admin)->get(route('patient.profile.show'))->assertForbidden();
    $this->actingAs($admin)->get(route('patient.portal.coming-soon'))->assertRedirect(route('dashboard'));
});

it('keeps staff away from the patient-only profile but redirects the dashboard', function () {
    $staff = phase3aUser('staff');

    $this->actingAs($staff)->get(route('patient.profile.show'))->assertForbidden();
    $this->actingAs($staff)->get(route('patient.portal.coming-soon'))->assertRedirect(route('dashboard'));
});

it('denies an unknown role on both portal pages', function () {
    $unknown = phase3aUser('clinic_observer');

    $this->actingAs($unknown)->get(route('patient.portal.coming-soon'))->assertForbidden();
    $this->actingAs($unknown)->get(route('patient.profile.show'))->assertForbidden();
});

it('refuses an unlinked patient on both portal pages with the friendly message', function () {
    $user = phase3aUser('patient');

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $this->actingAs($user)->get(route('patient.profile.show'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');
});

it('refuses a patient linked to an archived record on both portal pages', function () {
    $user = phase3aUser('patient');
    $patient = phase3aPatient();
    phase3aLink($patient, $user);
    $patient->delete();

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $this->actingAs($user)->get(route('patient.profile.show'))
        ->assertForbidden()
        ->assertSee('Please contact the clinic for assistance.');
});

it('treats a soft-deleted user as a guest on both portal pages', function () {
    $user = phase3aUser('patient');
    $patient = phase3aPatient();
    phase3aLink($patient, $user);
    $user->delete();

    // Real session rehydration: EloquentUserProvider applies the
    // SoftDeletes scope, so auth middleware resolves a guest.
    $sessionKey = 'login_web_' . sha1(SessionGuard::class);

    $this->withSession([$sessionKey => $user->id])
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('login'));

    $this->withSession([$sessionKey => $user->id])
        ->get(route('patient.profile.show'))
        ->assertRedirect(route('login'));
});

it('sends a flagged patient to the mandatory password change from both portal pages', function () {
    $user = phase3aUser('patient', flagged: true);
    $patient = phase3aPatient();
    phase3aLink($patient, $user);

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $this->actingAs($user)->get(route('patient.profile.show'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    // The redirect target is the change form, never dashboard content.
    $this->actingAs($user)->get(route('patient.profile.show'))
        ->assertRedirect(route('patient.change-initial-password.edit'))
        ->assertSessionMissing('patient-portal');
});

it('ignores any patient id supplied by the request', function () {
    [$user, $patient] = phase3aEligible();

    $other = phase3aPatient([
        'first_name' => 'Foreigner',
        'last_name' => 'Outsider',
        'email' => 'foreigner@phase3a.test',
    ]);

    // Query parameter: the logged-in patient still sees only their own record.
    $this->actingAs($user)
        ->get(route('patient.profile.show') . '?patient_id=' . $other->id)
        ->assertOk()
        ->assertSee($patient->first_name)
        ->assertDontSee('Foreigner')
        ->assertDontSee('Outsider');

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon') . '?patient_id=' . $other->id)
        ->assertOk()
        ->assertSee('Welcome, ' . $patient->first_name . '!')
        ->assertDontSee('Foreigner');

    // There is no ID-based profile URL to reach.
    $this->actingAs($user)->get('/patient/profile/' . $other->id)->assertNotFound();
    $this->actingAs($user)->get('/patient/profile/' . $patient->id)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Regression — Phases 2B / 2C / 2D, clinic routes, logout
|--------------------------------------------------------------------------
*/

it('keeps Phase 2B account creation working', function () {
    $staff = phase3aUser('staff');
    $patient = phase3aPatient(['email' => 'candidate@phase3a.test']);

    $this->actingAs($staff)->post(route('patients.account.store', $patient->id), [
        'email' => 'phase3a.newaccount@phase3a.test',
    ])->assertRedirect(route('patients.show', $patient->id));

    $created = User::where('email', 'phase3a.newaccount@phase3a.test')->first();

    expect($created)->not->toBeNull()
        ->and($created->role)->toBe('patient')
        ->and((bool) $created->must_change_password)->toBeTrue();
});

it('keeps the Phase 2C flagged-patient login chain working', function () {
    $user = phase3aUser('patient', flagged: true);
    $patient = phase3aPatient();
    phase3aLink($patient, $user);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

it('keeps the Phase 2D Patient Login Account section on the clinic profile', function () {
    $admin = phase3aUser('admin');
    $patient = phase3aPatient();

    $this->actingAs($admin)->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertSee('Patient Login Account');
});

it('keeps clinic routes protected', function () {
    // Guest is bounced from the clinic patient list.
    $this->get(route('patients.index'))->assertRedirect(route('login'));

    // Admin remains forbidden on the staff-only clinical list.
    $admin = phase3aUser('admin');
    $this->actingAs($admin)->get(route('patients.index'))->assertForbidden();
});

it('keeps logout working for a portal patient', function () {
    [$user] = phase3aEligible();

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));
});
