<?php

use App\Models\Patient;
use App\Models\User;

beforeEach(function () {
    Patient::withoutEvents(function () {
        Patient::query()->forceDelete();
    });
});

it('denies admin access to patients.index', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('patients.index'));

    $response->assertForbidden();
});

it('denies admin access to patients.create', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('patients.create'));

    $response->assertForbidden();
});

it('denies admin access to patients.store', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(route('patients.store'), [
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'birthdate' => '1995-01-01',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 1,
        'para' => 0,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
    ]);

    $response->assertForbidden();
});

it('allows staff access to patients.index', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get(route('patients.index'));

    $response->assertOk();
});

it('allows staff access to patients.create', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get(route('patients.create'));

    $response->assertOk();
});

it('allows both staff and admin access to patients.show', function () {
    $patient = Patient::create([
        'first_name' => 'Ana',
        'last_name' => 'Dela Cruz',
        'birthdate' => '1995-01-01',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 1,
        'para' => 0,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
    ]);

    $staff = User::factory()->create(['role' => 'staff']);
    $staffResponse = $this->actingAs($staff)->get(route('patients.show', $patient));
    $staffResponse->assertOk();

    $admin = User::factory()->create(['role' => 'admin']);
    $adminResponse = $this->actingAs($admin)->get(route('patients.show', $patient));
    $adminResponse->assertOk();
});

it('allows staff to access patients.store (middleware passes)', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->post(route('patients.store'), [
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'birthdate' => '1995-06-15',
        'age' => 31,
        'address' => 'Manila',
        'contact_number' => '09181234567',
        'civil_status' => 'Married',
        'philhealth_member' => '0',
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
    ]);

    expect($response->status())->not->toBe(403);
});

it('allows admin access to view-only patient routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('patients.delivered'));
    $response->assertOk();

    $response = $this->actingAs($admin)->get(route('risk.monitoring'));
    $response->assertOk();

    $response = $this->actingAs($admin)->get(route('audit-logs.index'));
    $response->assertOk();

    $response = $this->actingAs($admin)->get(route('referrals.index'));
    $response->assertOk();
});
