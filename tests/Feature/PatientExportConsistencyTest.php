<?php

use App\Models\MedicalHistory;
use App\Models\Baby;
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

function parsePatientCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $rows = [];
    while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        if ($row === null || $row === [null]) {
            continue;
        }
        $rows[] = array_map(static fn ($cell) => (string) $cell, $row);
    }
    fclose($handle);

    return $rows;
}

function csvSections(array $rows): array
{
    $sections = [];

    foreach (array_slice($rows, 1) as $row) {
        if (!in_array($row[0], $sections, true)) {
            $sections[] = $row[0];
        }
    }

    return $sections;
}

function csvRows(array $rows, string $section): array
{
    return array_values(array_filter(
        array_slice($rows, 1),
        static fn (array $row): bool => $row[0] === $section
    ));
}

function csvValue(array $rows, string $section, string $field): ?string
{
    foreach (csvRows($rows, $section) as $row) {
        if ($row[1] === $field) {
            return $row[2];
        }
    }

    return null;
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

it('csv exports both visits newest first and marks only the latest as current', function () {
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

    exportVisit($patient->id, [
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
    $rows = parsePatientCsv($content);

    expect(csvSections($rows))->toBe([
        'Patient Information',
        'Current Pregnancy',
        'Medical History',
        'Prenatal Visit 1 (Latest)',
        'Prenatal Visit 2',
        'Risk Assessment',
        'Export',
    ]);

    // The older encounter stays in the export, but only the newest one is
    // flagged current and only the newest one drives the Risk Assessment.
    expect($content)->toContain('Newer CSV assessment text')
        ->and($content)->toContain('Older CSV assessment text')
        ->and(strpos($content, 'Newer CSV assessment text'))
        ->toBeLessThan(strpos($content, 'Older CSV assessment text'))
        ->and(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Is Latest Visit'))->toBe('Yes')
        ->and(csvValue($rows, 'Prenatal Visit 2', 'Is Latest Visit'))->toBe('No')
        ->and(csvValue($rows, 'Risk Assessment', 'Risk Level'))->toBe('HIGH')
        ->and(csvValue($rows, 'Risk Assessment', 'Decision Source'))->toBe('Clinical Rules');
});

it('print record uses the newer visit when two visits share the same visit date', function () {
    $user = exportStaff();
    $patient = exportPatient();

    exportVisit($patient->id, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'assessment' => 'Older print assessment text',
    ]);

    exportVisit($patient->id, [
        'created_at' => now(),
        'updated_at' => now(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Newer print assessment text',
    ]);

    $response = $this->actingAs($user)->get(route('patients.print', $patient->id));

    $response->assertOk();
    $response->assertSeeText('PATIENT RECORD');
    $response->assertSeeText('Newer print assessment text');
    $response->assertDontSeeText('Older print assessment text');
});

it('uses one-decimal kilogram formatting across profile history print and exports', function () {
    $user = exportStaff();
    $patient = exportPatient([
        'status' => 'DELIVERED',
        'delivery_date' => '2026-09-01',
    ]);
    $visit = exportVisit($patient->id, [
        'visit_date' => '2026-09-01',
        'weight' => 60.5,
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);
    Baby::create([
        'patient_id' => $patient->id,
        'date_of_birth' => '2026-09-01',
        'time_of_birth' => '09:30',
        'birth_weight' => '3.25',
    ]);

    $profile = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $profile->assertOk()->assertSee('60.5 kg')->assertSee('3.3 kg');

    $activePatient = exportPatient([
        'first_name' => 'Active',
        'status' => 'ONGOING',
    ]);
    exportVisit($activePatient->id, [
        'visit_date' => '2026-09-01',
        'weight' => 60.5,
    ]);

    $visitIndex = $this->actingAs($user)->get(route('prenatal-visits.index'));
    $visitIndex->assertOk()->assertSee('60.5 kg');

    $babyInformation = $this->actingAs($user)->get(route('patients.delivered.babies', $patient->id));
    $babyInformation->assertOk()->assertSee('3.3 kg');

    $babyPrint = $this->actingAs($user)->get(route('patients.delivered.print-babies', $patient->id));
    $babyPrint->assertOk()->assertSee('3.3 kg');

    $admin = User::factory()->create(['role' => 'admin']);
    $pregnancyView = $this->actingAs($admin)
        ->get(route('view-all-records.pregnancy', $patient->id));
    $pregnancyView->assertOk()->assertSee('60.5 kg')->assertSee('3.3 kg');

    $pregnancyPrint = $this->actingAs($admin)
        ->get(route('view-all-records.pregnancy.print', $patient->id));
    $pregnancyPrint->assertOk()->assertSee('60.5 kg')->assertSee('3.3 kg');

    $visitPrint = $this->actingAs($user)->get(route('prenatal-visits.print', $visit->id));
    $visitPrint->assertOk()->assertSee('60.5 kg');

    $csv = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);
    $csv->assertOk();
    $csvRows = parsePatientCsv($csv->getContent());
    expect(csvValue($csvRows, 'Prenatal Visit 1 (Latest)', 'Weight'))->toBe('60.5 kg')
        ->and(csvValue($csvRows, 'Baby 1', 'Birth Weight'))->toBe('3.3 kg')
        ->and(csvValue($csvRows, 'Baby 1', 'Birth Length'))->toBe('')
        ->and($csv->getContent())->not->toContain('00:00:00');

    $print = $this->actingAs($user)->get(route('patients.print', $patient->id));
    $print->assertOk();
    $print->assertSee('60.5 kg')->assertSee('3.3 kg');
});

it('browser print route serves every patient record section', function () {
    $user = exportStaff();
    $patient = exportPatient([
        'status' => 'DELIVERED',
        'delivery_date' => '2026-09-01',
        'address' => null,
        'address_line' => '123 Mabini St., Green Village',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);
    Baby::create([
        'patient_id' => $patient->id,
        'date_of_birth' => '2026-09-01',
        'time_of_birth' => '09:30',
        'birth_weight' => '3.25',
    ]);

    exportVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Print route assessment text',
    ]);

    $response = $this->actingAs($user)->get(route('patients.print', $patient->id));

    $response->assertOk();
    $response->assertSee('PATIENT RECORD');

    foreach ([
        '1. Patient Information',
        '2. Current Pregnancy',
        '3. Latest Prenatal Visit',
        '4. Supporting Records Summary',
        '5. Risk Assessment Summary',
        '6. Clinical Decision Summary',
        '7. Baby Information',
        'Safety Disclaimer',
        'Clinical Rules',
        'Decision Source',
        'not a medical diagnosis',
        'Print route assessment text',
        '123 Mabini St., Green Village',
        'Barangay San Jose',
        'San Jose del Monte',
    ] as $expected) {
        $response->assertSee($expected);
    }

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('patients.print', $patient->id))
        ->assertOk();
});

it('browser print route returns 404 for an unknown patient', function () {
    $this->actingAs(exportStaff())
        ->get(route('patients.print', 999999))
        ->assertNotFound();
});

it('download endpoint accepts csv only after the pdf path was retired', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    $pdf = $this->actingAs($user)->postJson(route('patients.download', $patient->id), ['format' => 'pdf']);
    $pdf->assertStatus(422);
    $pdf->assertJsonValidationErrors('format');

    $csv = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);
    $csv->assertOk();
    expect($csv->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv->headers->get('Content-Disposition'))->toContain('attachment');
});

it('csv export uses the structured Section, Field, Value contract', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);
    exportVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'assessment' => 'Contract assessment text',
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Type'))->toContain('charset=UTF-8')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');

    $content = $response->getContent();

    // UTF-8 BOM so Excel renders degree signs and bullets correctly.
    expect(substr($content, 0, 3))->toBe("\xEF\xBB\xBF");

    $rows = parsePatientCsv($content);

    expect($rows[0])->toBe(['Section', 'Field', 'Value']);

    foreach ($rows as $row) {
        expect($row)->toHaveCount(3);
        foreach ($row as $cell) {
            expect($cell)->not->toContain("\n")
                ->and($cell)->not->toContain("\r");
        }
    }

    expect(csvSections($rows))->toBe([
        'Patient Information',
        'Current Pregnancy',
        'Medical History',
        'Prenatal Visit 1 (Latest)',
        'Risk Assessment',
        'Export',
    ]);

    expect(csvValue($rows, 'Export', 'Format Version'))->toBe('2')
        ->and(csvValue($rows, 'Export', 'Exported At'))->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/')
        ->and(csvValue($rows, 'Patient Information', 'Patient ID'))->toBe((string) $patient->id)
        ->and(csvValue($rows, 'Medical History', 'Recorded'))->toBe('Yes')
        ->and(csvValue($rows, 'Risk Assessment', 'Identified Risk Factor'))->toBe('Anemia')
        ->and(csvValue($rows, 'Risk Assessment', 'Next Visit Status'))->toBe('Not scheduled');

    // Nothing excluded from the contract may leak into the file.
    expect($content)->not->toContain('Safety Disclaimer')
        ->and($content)->not->toContain('assessment_metadata')
        ->and($content)->not->toContain('assigned_staff_id')
        ->and($content)->not->toContain('deleted_at')
        ->and($content)->not->toContain('updated_at')
        ->and($content)->not->toContain('remember_token');
});

it('csv export reports an absent medical history without crashing', function () {
    $user = exportStaff();
    $patient = exportPatient();
    exportVisit($patient->id, ['risk_level' => 'LOW']);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();

    $rows = parsePatientCsv($response->getContent());

    expect(csvRows($rows, 'Medical History'))->toHaveCount(1)
        ->and(csvValue($rows, 'Medical History', 'Recorded'))->toBe('No')
        ->and(csvValue($rows, 'Medical History', 'Epilepsy'))->toBeNull();
});

it('csv export keeps every prenatal visit newest first', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    exportVisit($patient->id, [
        'visit_date' => '2025-05-01',
        'risk_level' => 'LOW',
        'assessment' => 'Oldest CSV assessment text',
    ]);
    exportVisit($patient->id, [
        'visit_date' => '2025-06-01',
        'risk_level' => 'LOW',
        'assessment' => 'Middle CSV assessment text',
    ]);
    exportVisit($patient->id, [
        'visit_date' => '2025-07-01',
        'risk_level' => 'HIGH',
        'assessment' => 'Newest CSV assessment text',
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();
    $content = $response->getContent();
    $rows = parsePatientCsv($content);

    expect(csvSections($rows))->toBe([
        'Patient Information',
        'Current Pregnancy',
        'Medical History',
        'Prenatal Visit 1 (Latest)',
        'Prenatal Visit 2',
        'Prenatal Visit 3',
        'Risk Assessment',
        'Export',
    ]);

    expect(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Visit Date'))->toBe('2025-07-01')
        ->and(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Is Latest Visit'))->toBe('Yes')
        ->and(csvValue($rows, 'Prenatal Visit 2', 'Visit Date'))->toBe('2025-06-01')
        ->and(csvValue($rows, 'Prenatal Visit 2', 'Is Latest Visit'))->toBe('No')
        ->and(csvValue($rows, 'Prenatal Visit 3', 'Visit Date'))->toBe('2025-05-01')
        ->and(csvValue($rows, 'Prenatal Visit 3', 'Is Latest Visit'))->toBe('No')
        ->and(csvValue($rows, 'Risk Assessment', 'Risk Level'))->toBe('HIGH');

    expect($content)->toContain('Newest CSV assessment text')
        ->and($content)->toContain('Middle CSV assessment text')
        ->and($content)->toContain('Oldest CSV assessment text');
});

it('csv export renders dates without a time component', function () {
    $user = exportStaff();
    $patient = exportPatient([
        'status' => 'DELIVERED',
        'delivery_date' => '2025-10-10',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);
    exportVisit($patient->id, [
        'visit_date' => '2025-10-01',
        'next_visit_date' => '2025-10-08',
        'repeat_bp_recorded_at' => now(),
    ]);
    Baby::create([
        'patient_id' => $patient->id,
        'first_name' => 'Baby',
        'last_name' => 'Test',
        'date_of_birth' => '2025-10-10',
        'time_of_birth' => '09:30',
        'birth_weight' => '3.25',
        'birth_length' => '49.0',
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();
    $content = $response->getContent();
    $rows = parsePatientCsv($content);

    expect($content)->not->toContain('00:00:00');

    expect(csvValue($rows, 'Patient Information', 'Birthdate'))->toBe('1995-01-01')
        ->and(csvValue($rows, 'Current Pregnancy', 'LMP'))->toBe('2025-01-01')
        ->and(csvValue($rows, 'Current Pregnancy', 'EDD'))->toBe('2025-10-08')
        ->and(csvValue($rows, 'Current Pregnancy', 'Delivery Date'))->toBe('2025-10-10')
        ->and(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Visit Date'))->toBe('2025-10-01')
        ->and(csvValue($rows, 'Risk Assessment', 'Recommended Follow-up'))->toBe('2025-10-08')
        ->and(csvValue($rows, 'Baby 1', 'Date of Birth'))->toBe('2025-10-10')
        ->and(csvValue($rows, 'Baby 1', 'Time of Birth'))->toBe('9:30 AM')
        ->and(csvValue($rows, 'Baby 1', 'Birth Length'))->toBe('49.0 cm');
});

it('csv export maps pregnancy status and next visit status', function () {
    $user = exportStaff();

    $referred = exportPatient([
        'first_name' => 'Referred',
        'status' => 'REFERRED',
        'delivery_date' => '2025-09-01',
    ]);
    MedicalHistory::create(['patient_id' => $referred->id]);
    exportVisit($referred->id, ['visit_date' => '2025-08-01', 'next_visit_date' => '2025-09-15']);

    $referredRows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $referred->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(csvValue($referredRows, 'Current Pregnancy', 'Pregnancy Status'))->toBe('Referred')
        ->and(csvValue($referredRows, 'Risk Assessment', 'Next Visit Status'))->toBe('Referred');

    $delivered = exportPatient([
        'first_name' => 'Delivered',
        'status' => 'DELIVERED',
        'delivery_date' => '2025-09-01',
    ]);
    MedicalHistory::create(['patient_id' => $delivered->id]);
    exportVisit($delivered->id, ['visit_date' => '2025-08-01', 'next_visit_date' => '2025-09-15']);

    $deliveredRows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $delivered->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(csvValue($deliveredRows, 'Current Pregnancy', 'Pregnancy Status'))->toBe('Delivered')
        ->and(csvValue($deliveredRows, 'Risk Assessment', 'Next Visit Status'))->toBe('Delivered');

    $overdue = exportPatient(['first_name' => 'Overdue']);
    MedicalHistory::create(['patient_id' => $overdue->id]);
    exportVisit($overdue->id, [
        'visit_date' => now()->subWeeks(6)->toDateString(),
        'next_visit_date' => now()->subWeek()->toDateString(),
    ]);

    $overdueRows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $overdue->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(csvValue($overdueRows, 'Current Pregnancy', 'Pregnancy Status'))->toBe('Ongoing')
        ->and(csvValue($overdueRows, 'Risk Assessment', 'Next Visit Status'))->toBe('Overdue');
});

it('csv export lists each baby and omits the section when no baby exists', function () {
    $user = exportStaff();

    $ongoing = exportPatient(['first_name' => 'NoBaby']);
    MedicalHistory::create(['patient_id' => $ongoing->id]);

    $ongoingRows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $ongoing->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(array_filter(
        csvSections($ongoingRows),
        static fn (string $section): bool => str_starts_with($section, 'Baby')
    ))->toBe([]);

    $delivered = exportPatient([
        'first_name' => 'Twins',
        'status' => 'DELIVERED',
        'delivery_date' => '2025-10-10',
    ]);
    MedicalHistory::create(['patient_id' => $delivered->id]);
    Baby::create([
        'patient_id' => $delivered->id,
        'first_name' => 'First',
        'last_name' => 'Test',
        'sex' => 'Female',
        'date_of_birth' => '2025-10-10',
        'time_of_birth' => '09:30',
        'birth_weight' => '2.50',
    ]);
    Baby::create([
        'patient_id' => $delivered->id,
        'first_name' => 'Second',
        'last_name' => 'Test',
        'sex' => 'Male',
        'date_of_birth' => '2025-10-10',
        'time_of_birth' => '09:32',
        'birth_weight' => '2.40',
    ]);

    $rows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $delivered->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(csvValue($rows, 'Baby 1', 'Full Name'))->toBe('First Test')
        ->and(csvValue($rows, 'Baby 1', 'Birth Weight'))->toBe('2.5 kg')
        ->and(csvValue($rows, 'Baby 2', 'Full Name'))->toBe('Second Test')
        ->and(csvValue($rows, 'Baby 2', 'Birth Weight'))->toBe('2.4 kg');
});

it('csv export includes birth plan and ultrasound records without storage paths', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    \App\Models\BirthPlan::create([
        'patient_id' => $patient->id,
        'planned_visits' => 8,
        'deliver_in_clinic' => 1,
        'delivery_location' => 'Birthing home',
        'transportation' => 'Private vehicle',
        'transport_cost' => 1,
        'payment_method' => 'Cash',
        'saving_started' => 1,
        'birth_companion' => 'Husband',
        'caregiver_home' => 'Mother',
        'plan_more_children' => 1,
        'number_more_children' => 2,
        'knows_fp_method' => 1,
        'used_fp_before' => 1,
        'family_planning_method' => 'Pills',
        'fp_source' => 'Clinic orientation',
        'notes' => "First note line\nSecond note line",
    ]);

    \App\Models\Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => '2025-06-01',
        'gestational_age_scan' => 22.5,
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Anterior',
        'fetal_heartbeat' => 'Normal',
        'fetal_movement' => 'Normal',
        'estimated_fetal_weight' => 500,
        'remarks' => 'Unremarkable',
        'report_file' => 'uploads/scan-report.pdf',
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();
    $content = $response->getContent();
    $rows = parsePatientCsv($content);

    expect(csvValue($rows, 'Birth Plan', 'Birth Companion'))->toBe('Husband')
        ->and(csvValue($rows, 'Birth Plan', 'Caregiver at Home'))->toBe('Mother')
        ->and(csvValue($rows, 'Birth Plan', 'Deliver in Clinic'))->toBe('Yes')
        ->and(csvValue($rows, 'Birth Plan', 'Notes'))->toBe('First note line | Second note line')
        ->and(csvValue($rows, 'Ultrasound 1', 'Gestational Age at Scan'))->toBe('22.5 wks')
        ->and(csvValue($rows, 'Ultrasound 1', 'Estimated Fetal Weight'))->toBe('500')
        ->and(csvValue($rows, 'Ultrasound 1', 'Report On File'))->toBe('Yes');

    expect($content)->not->toContain('uploads/scan-report.pdf')
        ->and($content)->not->toContain('report_file')
        ->and($content)->not->toContain('report_image')
        ->and($content)->not->toContain('storage/');
});

it('csv export neutralises spreadsheet formula injection', function () {
    $user = exportStaff();
    $patient = exportPatient([
        'address' => '=1+1',
        'address_line' => null,
        'barangay' => null,
        'city_municipality' => null,
        'middle_name' => 'Maria',
        'email' => '@lead',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv']);

    $response->assertOk();
    $rows = parsePatientCsv($response->getContent());

    expect(csvValue($rows, 'Patient Information', 'Address Line'))->toBe("'=1+1")
        ->and(csvValue($rows, 'Patient Information', 'Email Address'))->toBe("'@lead")
        ->and(csvValue($rows, 'Patient Information', 'Full Name'))->toBe('Export Maria Test')
        ->and(csvValue($rows, 'Current Pregnancy', 'Gravida'))->toBe('2');
});

it('csv export renders the stored blood-pressure assessment rows', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    exportVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Severe-range blood-pressure finding'],
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_sys' => 160,
        'bp_dia' => 110,
        'repeat_bp_sys' => 170,
        'repeat_bp_dia' => 115,
        'bp_verification_status' => 'REPEAT_COMPLETED',
        'bp_assessment' => [
            'reason_code' => 'BP-URG',
            'risk_level' => 'HIGH',
            'urgency' => 'URGENT_CLINICAL_REVIEW',
            'label' => 'Severe-range blood-pressure finding',
            'clinical_interpretation' => 'The recorded reading met the severe-range screening threshold.',
            'suggested_action' => 'Immediate qualified assessment and referral evaluation are recommended.',
            'threshold' => 'Systolic >= 160 or diastolic >= 110',
        ],
    ]);

    $rows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    expect(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Blood Pressure'))->toBe('160/110')
        ->and(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'Repeat Blood Pressure'))->toBe('170/115')
        ->and(csvValue($rows, 'Prenatal Visit 1 (Latest)', 'BP Verification Status'))->toBe('REPEAT COMPLETED')
        ->and(csvValue($rows, 'Risk Assessment', 'Urgency'))->toBe('Urgent Clinical Review')
        ->and(csvValue($rows, 'Risk Assessment', 'Identified Risk Factor'))->toBe('Severe-range blood-pressure finding')
        ->and(csvValue($rows, 'Risk Assessment', 'BP Classification'))->toBe('Severe-range blood-pressure finding')
        ->and(csvValue($rows, 'Risk Assessment', 'BP Interpretation'))->toBe('The recorded reading met the severe-range screening threshold.')
        ->and(csvValue($rows, 'Risk Assessment', 'BP Action'))->toBe('Immediate qualified assessment and referral evaluation are recommended.')
        ->and(csvValue($rows, 'Risk Assessment', 'Triggered Clinical Rule'))->toBe('Severe-range blood-pressure finding');
});

it('csv export renders structured factor and interaction explainability rows', function () {
    $user = exportStaff();
    $patient = exportPatient();
    MedicalHistory::create(['patient_id' => $patient->id]);

    exportVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Teenage pregnancy (under 19)'],
        'factor_evidence' => [[
            'code' => 'AGE-Y',
            'label' => 'Teenage pregnancy (under 19)',
            'category' => 'MATERNAL_DEMOGRAPHICS',
            'source_type' => 'PATIENT',
            'observed_value' => 18,
            'threshold_or_rule' => 'Age < 19 years',
            'decision_effect' => 'HIGH_RISK',
            'explanation' => 'Age requires age-sensitive assessment.',
            'suggested_action' => 'Provide age-appropriate antenatal care.',
        ]],
        'assessment_metadata' => [
            'interaction_evidence' => [[
                'code' => 'INT-BP-DM',
                'label' => 'Elevated blood pressure with diabetes',
                'required_factor_codes' => ['BP-H', 'DM-01'],
                'observed_context' => ['ultrasound_inputs.amniotic_fluid' => 'Low'],
                'explanation' => 'Both findings were identified together.',
                'suggested_action' => 'Coordinate qualified clinical review.',
            ]],
        ],
    ]);

    $rows = parsePatientCsv(
        $this->actingAs($user)->post(route('patients.download', $patient->id), ['format' => 'csv'])
            ->assertOk()->getContent()
    );

    $factorValues = array_column(
        array_filter(csvRows($rows, 'Risk Assessment'), static fn (array $row): bool => $row[1] === 'Structured Clinical Factor'),
        2
    );
    $interactionValues = array_column(
        array_filter(csvRows($rows, 'Risk Assessment'), static fn (array $row): bool => $row[1] === 'Clinical Interaction'),
        2
    );

    expect($factorValues)->toHaveCount(1);
    expect($factorValues[0])
        ->toContain('Teenage pregnancy (under 19) (AGE-Y)')
        ->toContain('Source: Maternal demographics')
        ->toContain('Observed: 18')
        ->toContain('Rule / Threshold: Age < 19 years')
        ->toContain('Action: Provide age-appropriate antenatal care.');

    expect($interactionValues)->toHaveCount(1);
    expect($interactionValues[0])
        ->toContain('Elevated blood pressure with diabetes (INT-BP-DM)')
        ->toContain('Factors: BP-H, DM-01')
        ->toContain('Amniotic fluid: Low')
        ->toContain('Both findings were identified together.')
        ->toContain('Action: Coordinate qualified clinical review.');
});
