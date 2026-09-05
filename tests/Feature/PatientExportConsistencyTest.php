<?php

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function exportPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Export',
        'middle_name' => '',
        'last_name' => 'Test',
        'birthdate' => '1995-01-01',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'export@example.com',
        'civil_status' => 'Married',
        'philhealth_member' => 0,
        'philhealth_number' => null,
        'gravida' => 2,
        'para' => 1,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
        'status' => 'ONGOING',
    ], $overrides));
}

function exportVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
    ], $overrides));
}

function exportStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

it('profile uses the newer visit when two visits share the same visit date', function () {
    $user = exportStaff();
    $patient = exportPatient();

    exportVisit($patient->id, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'assessment' => 'Older profile assessment text',
    ]);

    exportVisit($patient->id, [
        'created_at' => now(),
        'updated_at' => now(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Newer profile assessment text',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Newer profile assessment text');
    $response->assertSeeText('HIGH RISK');
    $response->assertDontSeeText('LOW RISK');
});

it('csv uses the newer visit when two visits share the same visit date', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    exportVisit($patient->id, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'assessment' => 'Older CSV assessment text',
    ]);

    $newer = exportVisit($patient->id, [
        'created_at' => now(),
        'updated_at' => now(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Newer CSV assessment text',
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), [
        'format' => 'csv',
    ]);

    $response->assertOk();
    $content = $response->getContent();

    expect($content)->toContain('Newer CSV assessment text');
    expect($content)->not->toContain('Older CSV assessment text');
});

it('pdf view data uses the newer visit when two visits share the same visit date', function () {
    $user = exportStaff();
    $patient = exportPatient();

    exportVisit($patient->id, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'assessment' => 'Older PDF assessment text',
    ]);

    $newer = exportVisit($patient->id, [
        'created_at' => now(),
        'updated_at' => now(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Newer PDF assessment text',
    ]);

    $pdf = new class {
        public array $viewData = [];

        public function loadView(string $view, array $data = [], array $mergeData = []): static
        {
            $this->viewData = $data;

            return $this;
        }

        public function setPaper(mixed $paper, string $orientation = 'portrait'): static
        {
            return $this;
        }

        public function download(string $filename = 'document.pdf')
        {
            return response('pdf', 200);
        }
    };

    $this->app->instance('dompdf.wrapper', $pdf);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), [
        'format' => 'pdf',
    ]);

    $response->assertOk();

    expect($pdf->viewData)->toHaveKey('latestVisit');
    expect($pdf->viewData['latestVisit']->id)->toBe($newer->id);
    expect($pdf->viewData['latestVisit']->assessment)->toBe('Newer PDF assessment text');
});
