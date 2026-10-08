<?php

use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 1 Security Hardening — Deny-by-Default Clinic Route Authorization
|--------------------------------------------------------------------------
| The 'clinic' middleware allows ONLY role 'admin' or 'staff'. Patient and
| unknown roles must be denied on every internal clinic route, write
| attempts must not modify any record, guests stay subject to auth, and the
| pre-existing Admin-only / Staff-only rules must remain unchanged.
*/

beforeEach(function () {
    $this->withoutVite();

    $this->clinicPatient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'birthdate' => '1995-01-01',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 1,
        'para' => 0,
        'lmp' => '2026-06-01',
        'edd' => '2027-03-08',
        'status' => 'ONGOING',
    ]);
});

function clinicSecurityInternalUrls(Patient $patient): array
{
    return [
        route('dashboard'),
        route('patients.index'),
        route('patients.show', $patient->id),
        route('patients.print', $patient->id),
        route('patients.delivered'),
        route('patients.delivered.history', $patient->id),
        route('prenatal-visits.index'),
        route('prenatal-visits.print', 1),
        route('medical-histories.index'),
        route('birth-plans.index'),
        route('ultrasound.file', ['id' => 1, 'type' => 'image']),
        route('pregnancy-outcomes.index'),
        route('referrals.index'),
        route('referrals.show', 1),
        route('risk.monitoring'),
        route('risk.monitoring.analytics'),
        route('staff.index'),
        route('audit-logs.index'),
        route('view-all-records.index'),
        route('locations.search'),
        route('profile.edit'),
    ];
}

test('guests remain subject to authentication on internal clinic routes', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('patients.index'))->assertRedirect(route('login'));
    $this->get(route('referrals.index'))->assertRedirect(route('login'));
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));
    $this->post(route('notifications.markAllRead'))->assertRedirect(route('login'));
});

test('patient role is denied every internal clinic route', function () {
    $user = User::factory()->create(['role' => 'patient']);

    foreach (clinicSecurityInternalUrls($this->clinicPatient) as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    $this->actingAs($user)
        ->getJson(route('risk.monitoring.analytics'))
        ->assertForbidden();
});

test('unknown role values are denied every internal clinic route', function () {
    $user = User::factory()->create(['role' => 'clinic_observer']);

    foreach (clinicSecurityInternalUrls($this->clinicPatient) as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }

    $this->actingAs($user)
        ->getJson(route('dashboard'))
        ->assertForbidden();
});

test('patient role write attempts are denied and change no records', function () {
    $user = User::factory()->create(['role' => 'patient']);

    $this->actingAs($user)->post(route('patients.store'), [])->assertForbidden();
    expect(Patient::count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('patients.destroy', $this->clinicPatient->id))
        ->assertForbidden();
    $this->actingAs($user)
        ->post(route('patients.restore', $this->clinicPatient->id))
        ->assertForbidden();
    expect(Patient::first()->trashed())->toBeFalse();

    $this->actingAs($user)->post(route('referrals.store'), [])->assertForbidden();
    expect(Referral::count())->toBe(0);

    $this->actingAs($user)
        ->put(route('ultrasound.update', 999), [])
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('notifications.markAllRead'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Hacked Name'])
        ->assertForbidden();
    expect($user->fresh()->name)->not->toBe('Hacked Name');

    $this->actingAs($user)->post(route('staff.store'), [])->assertForbidden();
    expect(User::where('role', 'staff')->count())->toBe(0);
});

test('unknown role write attempts are denied and change no records', function () {
    $user = User::factory()->create(['role' => 'superuser']);

    $this->actingAs($user)->post(route('patients.store'), [])->assertForbidden();
    expect(Patient::count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('patients.destroy', $this->clinicPatient->id))
        ->assertForbidden();
    expect(Patient::first()->trashed())->toBeFalse();

    $this->actingAs($user)->post(route('staff.store'), [])->assertForbidden();
    expect(User::where('role', 'staff')->count())->toBe(0);
});

test('patient role can only reach the holding page and logout', function () {
    $user = User::factory()->create(['role' => 'patient']);

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Patient Portal')
        ->assertSee('not available yet')
        ->assertDontSee('sidebar-nav')
        ->assertDontSee('Manage Staff')
        ->assertDontSee('Audit Logs')
        ->assertDontSee('Prenatal Visits')
        ->assertDontSee('Risk Monitoring');

    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
});

test('existing admin and staff authorization rules are preserved', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    // Dashboard stays open to both clinic roles.
    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($staff)->get(route('dashboard'))->assertOk();

    // patients.index stays Staff-only (Admin is still forbidden).
    $this->actingAs($staff)->get(route('patients.index'))->assertOk();
    $this->actingAs($admin)->get(route('patients.index'))->assertForbidden();

    // Staff management and audit logs stay Admin-only.
    $this->actingAs($admin)->get(route('staff.index'))->assertOk();
    $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    $this->actingAs($staff)->get(route('audit-logs.index'))->assertForbidden();

    // Risk monitoring page stays Admin-only; analytics stays open to both.
    $this->actingAs($admin)->get(route('risk.monitoring'))->assertOk();
    $this->actingAs($staff)->get(route('risk.monitoring'))->assertForbidden();
    $this->actingAs($staff)
        ->getJson(route('risk.monitoring.analytics', ['year' => 2026, 'risk_type' => 'LOW']))
        ->assertOk();
    $this->actingAs($admin)
        ->getJson(route('risk.monitoring.analytics', ['year' => 2026, 'risk_type' => 'LOW']))
        ->assertOk();

    // Profile stays open to both clinic roles.
    $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    $this->actingAs($staff)->get(route('profile.edit'))->assertOk();
});
