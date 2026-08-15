<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function prenatalLongRiskPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Lora',
        'last_name' => 'Risk',
        'age' => 29,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

it('renders a long risk value in full with wrapping enabled and keeps all action icons', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = prenatalLongRiskPatient();

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 24,
        'risk_level' => 'THE SYSTEM CANNOT FIND THE PATH SPECIFIED.',
    ]);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('THE SYSTEM CANNOT FIND THE PATH SPECIFIED.');
    expect($html)->toContain('status-badge-wrap');

    expect($html)->toContain(route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits']));
    expect($html)->toContain(route('prenatal-visits.edit', $visit->id));
    expect($html)->toContain(route('prenatal-visits.destroy', $visit->id));
    expect($html)->toContain('title="View"');
    expect($html)->toContain('title="Edit"');
    expect($html)->toContain('title="Delete"');
});

it('keeps the normal risk badge variants unchanged for HIGH, LOW, and ASSESSMENT INCOMPLETE', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = prenatalLongRiskPatient();

    foreach (['HIGH', 'LOW', 'ASSESSMENT INCOMPLETE'] as $risk) {
        PrenatalVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateString(),
            'bp_sys' => 120,
            'bp_dia' => 80,
            'weight' => 60,
            'gestational_age' => 24,
            'risk_level' => $risk,
        ]);
    }

    $html = $this->actingAs($user)->get(route('prenatal-visits.index'))->getContent();

    expect($html)->toContain('status-badge-danger');
    expect($html)->toContain('status-badge-success');
    expect($html)->toContain('status-badge-warning');
});