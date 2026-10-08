<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 1 Security Hardening — Login & Redirect Safety
|--------------------------------------------------------------------------
| Patient and unknown roles must never fall through to the clinic
| dashboard through any redirect chain. Admin/Staff keep their existing
| dashboard destination; patients reach only the neutral holding page;
| unknown roles are denied with 403 and no session.
*/

test('admin login lands on the clinic dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('staff login lands on the clinic dashboard', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->post('/login', [
        'email' => $staff->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('patient login lands on the neutral portal holding page', function () {
    $patientUser = User::factory()->create(['role' => 'patient']);

    $this->post('/login', [
        'email' => $patientUser->email,
        'password' => 'password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('not available yet');
});

test('unknown role login is denied with access denied and no session', function () {
    $unknown = User::factory()->create(['role' => 'superuser']);

    $this->post('/login', [
        'email' => $unknown->email,
        'password' => 'password',
    ])->assertForbidden();

    $this->assertGuest();
});

test('authenticated patient is redirected away from guest routes to the holding page', function () {
    $patientUser = User::factory()->create(['role' => 'patient']);

    $this->actingAs($patientUser)->get('/login')
        ->assertRedirect(route('patient.portal.coming-soon'));
    $this->actingAs($patientUser)->get('/register')
        ->assertRedirect(route('patient.portal.coming-soon'));
    $this->actingAs($patientUser)->get('/forgot-password')
        ->assertRedirect(route('patient.portal.coming-soon'));
});

test('authenticated clinic staff keep the existing guest-route redirect to the dashboard', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get('/login')
        ->assertRedirect(route('dashboard'));
});

test('root URL sends each role to its safe destination', function () {
    $this->get('/')->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/')
        ->assertRedirect(route('dashboard'));
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get('/')
        ->assertRedirect(route('dashboard'));
    $this->actingAs(User::factory()->create(['role' => 'patient']))->get('/')
        ->assertRedirect(route('patient.portal.coming-soon'));
    $this->actingAs(User::factory()->create(['role' => 'superuser']))->get('/')
        ->assertForbidden();
});

test('patient cannot reach the clinic dashboard through redirects', function () {
    $patientUser = User::factory()->create(['role' => 'patient']);

    $this->actingAs($patientUser)->get('/dashboard')->assertForbidden();
    $this->actingAs($patientUser)->get('/')->assertRedirect(route('patient.portal.coming-soon'));
});

test('holding page renders no clinic navigation or clinic data', function () {
    $patientUser = User::factory()->create(['role' => 'patient']);

    $this->actingAs($patientUser)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Patient Portal')
        ->assertSee('not available yet')
        ->assertSee('contact the clinic')
        ->assertDontSee('sidebar-nav')
        ->assertDontSee('Manage Staff')
        ->assertDontSee('Audit Logs')
        ->assertDontSee('Pregnancy Outcome Monitoring')
        ->assertDontSee('Total Patients');
});
