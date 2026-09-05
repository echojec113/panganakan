<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

it('shows delivered patients and separate Pending Referral indicators in risk monitoring', function () {
    $user = User::factory()->create(['role' => 'staff']);

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
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $referredPatient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'LOW',
        'risk_reasons' => ['Low risk'],
        'assessment' => 'Low risk',
        'next_visit_date' => now()->subDays(5)->toDateString(),
    ]);

    // Decoupled model: ONGOING patient with an active Pending referral.
    Referral::create([
        'patient_id' => $referredPatient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)->get(route('risk.monitoring'));

    $response->assertOk();
    $response->assertSeeText('Maria Dela Cruz');
    $response->assertSeeText('Delivered');
    $response->assertSeeText('Ana Santos');
    $response->assertSeeText('Pending Referral');
});

it('decouples referral creation from the pregnancy lifecycle', function () {
    $user = User::factory()->create(['role' => 'staff']);

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

    expect($patient->status)->toBe('ONGOING');
    $referral = Referral::latest('id')->first();
    expect($referral->patient_id)->toBe($patient->id);
    expect($referral->status)->toBe('Pending');
});

it('shows a Pending Referral indicator separately without suppressing overdue', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $patient = Patient::create([
        'first_name' => 'Clara',
        'last_name' => 'Mercado',
        'age' => 29,
        'address' => 'Test address',
        'contact_number' => '09131234567',
        'email' => 'clara@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Hypertension'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->subDays(3)->toDateString(),
    ]);

    $referral = Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertOk()
        ->assertSeeText('Pending Referral')
        ->assertSeeText('Overdue');

    app(\App\Services\ReferralFollowThroughService::class)
        ->complete($referral, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertOk()
        ->assertDontSeeText('Pending Referral')
        ->assertSeeText('Overdue');
});