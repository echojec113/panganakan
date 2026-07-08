<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

it('shows delivered and referred patients in risk monitoring with terminal labels', function () {
    $user = User::factory()->create();

    $deliveredPatient = Patient::create([
        'first_name' => 'Maria',
        'last_name' => 'Dela Cruz',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'maria@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'DELIVERED',
        'delivery_date' => now()->subDay()->toDateString(),
    ]);

    PrenatalVisit::create([
        'patient_id' => $deliveredPatient->id,
        'visit_date' => now()->subWeeks(2)->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Preeclampsia'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->subDays(2)->toDateString(),
    ]);

    $referredPatient = Patient::create([
        'first_name' => 'Ana',
        'last_name' => 'Santos',
        'age' => 31,
        'address' => 'Test address',
        'contact_number' => '09181234567',
        'email' => 'ana@example.com',
        'gravida' => 3,
        'para' => 2,
        'status' => 'REFERRED',
    ]);

    PrenatalVisit::create([
        'patient_id' => $referredPatient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'LOW',
        'risk_reasons' => ['Low risk'],
        'assessment' => 'Low risk',
        'next_visit_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = $this->actingAs($user)->get(route('risk.monitoring'));

    $response->assertOk();
    $response->assertSeeText('Maria Dela Cruz');
    $response->assertSeeText('Delivered');
    $response->assertSeeText('Ana Santos');
    $response->assertSeeText('Referred');
    $response->assertDontSeeText('Overdue');
});

it('marks a patient as referred when a referral is created', function () {
    $user = User::factory()->create();

    $patient = Patient::create([
        'first_name' => 'Liza',
        'last_name' => 'Reyes',
        'age' => 26,
        'address' => 'Test address',
        'contact_number' => '09191234567',
        'email' => 'liza@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);

    $response = $this->actingAs($user)->post(route('referrals.store'), [
        'patient_id' => $patient->id,
        'referred_to' => 'Provincial Hospital',
        'doctor_name' => 'Dr. Santos',
        'reason' => 'Needs specialist care',
        'notes' => 'Test referral',
        'date_referred' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('referrals.index'));
    $patient->refresh();

    expect($patient->status)->toBe('REFERRED');
    expect(Referral::latest('id')->first()->patient_id)->toBe($patient->id);
});
