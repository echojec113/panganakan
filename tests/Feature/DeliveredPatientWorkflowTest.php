<?php

use App\Models\Baby;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

it('groups delivered pregnancies by patient on the delivered patients list', function () {
    $user = User::factory()->create();

    $firstPregnancy = Patient::create([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Reyes',
        'birthdate' => '1998-01-10',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'DELIVERED',
        'delivery_date' => '2026-04-01',
    ]);

    $secondPregnancy = Patient::create([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Reyes',
        'birthdate' => '1998-01-10',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 3,
        'para' => 2,
        'status' => 'DELIVERED',
        'delivery_date' => '2026-07-01',
    ]);

    Baby::create(['patient_id' => $firstPregnancy->id, 'date_of_birth' => '2026-04-01', 'time_of_birth' => '08:00']);
    Baby::create(['patient_id' => $secondPregnancy->id, 'date_of_birth' => '2026-07-01', 'time_of_birth' => '08:00']);
    Baby::create(['patient_id' => $secondPregnancy->id, 'date_of_birth' => '2026-07-01', 'time_of_birth' => '08:01']);

    $response = $this->actingAs($user)->get(route('patients.delivered'));

    $response->assertOk();
    expect(substr_count($response->getContent(), 'Maria Santos Reyes'))->toBe(1);
    $response->assertSeeText('2');
    $response->assertSeeText('3');
    $response->assertSeeText('Jul 01, 2026');
    $response->assertSeeText('View History');
});

it('shows pregnancy history and baby information pages', function () {
    $user = User::factory()->create();

    $pregnancy = Patient::create([
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'birthdate' => '1997-02-10',
        'age' => 29,
        'address' => 'Test address',
        'contact_number' => '09181234567',
        'gravida' => 1,
        'para' => 1,
        'status' => 'DELIVERED',
        'delivery_date' => '2026-06-15',
        'philhealth_member' => true,
    ]);

    PrenatalVisit::create([
        'patient_id' => $pregnancy->id,
        'visit_date' => '2026-06-01',
        'risk_level' => 'LOW',
    ]);

    Baby::create([
        'patient_id' => $pregnancy->id,
        'first_name' => 'Baby',
        'last_name' => 'Cruz',
        'sex' => 'Female',
        'date_of_birth' => '2026-06-15',
        'time_of_birth' => '09:30',
        'birth_weight' => 2.80,
        'birth_length' => 48,
    ]);

    $history = $this->actingAs($user)->get(route('patients.delivered.history', $pregnancy->id));
    $history->assertOk();
    $history->assertSeeText('Pregnancy History');
    $history->assertSeeText('Ana Cruz');
    $history->assertSeeText('Completed Pregnancies (1)');
    $history->assertSeeText('Low');

    $babyInformation = $this->actingAs($user)->get(route('patients.delivered.babies', $pregnancy->id));
    $babyInformation->assertOk();
    $babyInformation->assertSeeText('Baby Information');
    $babyInformation->assertSeeText('Baby Cruz');
    $babyInformation->assertSeeText('Female');

    $print = $this->actingAs($user)->get(route('patients.delivered.print-babies', ['id' => $pregnancy->id, 'all' => 1]));
    $print->assertOk();
    $print->assertSeeText('Baby Information');
    $print->assertSeeText('Baby Cruz');
});
