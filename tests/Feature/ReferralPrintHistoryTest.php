<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

test('referral print orders actual patient referrals by date and id and includes archived prenatal sources', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = Patient::create(['first_name' => 'Referral', 'last_name' => 'History', 'age' => 28]);
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id, 'visit_date' => '2026-09-01',
        'bp_sys' => 140, 'bp_dia' => 90, 'weight' => 60,
        'gestational_age' => 30, 'risk_level' => 'HIGH',
    ]);
    $payload = [
        'patient_id' => $patient->id, 'created_by' => $staff->id,
        'referred_to' => str_repeat('LongFacility', 30), 'reason' => 'Stored referral reason',
        'referral_date' => '2026-09-10', 'status' => 'Pending',
    ];
    $manual = Referral::create($payload);
    $linked = Referral::create(array_merge($payload, [
        'prenatal_visit_id' => $visit->id,
        'assessment_snapshot' => ['risk_level' => 'HIGH', 'assessment' => 'Preserved assessment'],
    ]));
    $older = Referral::create(array_merge($payload, ['referral_date' => '2026-08-01']));
    $otherPatient = Patient::create(['first_name' => 'Other', 'last_name' => 'Patient', 'age' => 25]);
    Referral::create(array_merge($payload, ['patient_id' => $otherPatient->id, 'reason' => 'Other patient referral']));
    $visit->delete();

    $response = $this->actingAs($staff)->get(route('referrals.print', $older));
    $response->assertOk()
        ->assertViewHas('referralHistory', function ($history) use ($linked, $manual, $older) {
            expect($history->pluck('id')->all())->toBe([$linked->id, $manual->id, $older->id]);
            expect($history->first()->prenatalVisit->trashed())->toBeTrue();
            return true;
        })
        ->assertSee('Back to Referral')
        ->assertSee('Manual Referral')
        ->assertSee('Prenatal Visit Referral')
        ->assertSee('Preserved assessment')
        ->assertSee('images/logo.png')
        ->assertDontSee('Other patient referral');

    expect(substr_count($response->getContent(), 'class="referral-letter print-container print-selected"'))->toBe(1);
    expect($response->getContent())->not->toMatch('/<details[^>]*\sopen(?:\s|>)/');
    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);
});

test('a single referral prints without historical navigation and still requires authentication', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = Patient::create(['first_name' => 'Single', 'last_name' => 'Patient', 'age' => 25]);
    $referral = Referral::create([
        'patient_id' => $patient->id, 'created_by' => $staff->id,
        'referred_to' => 'Hospital', 'reason' => 'Specialist review',
        'referral_date' => '2026-09-01', 'status' => 'Completed',
    ]);
    $this->get(route('referrals.print', $referral))->assertRedirect(route('login'));
    $this->actingAs($staff)->get(route('referrals.print', $referral))
        ->assertOk()->assertSee('Specialist review')->assertDontSee('<details', false);
});
