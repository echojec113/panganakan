<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Models\MedicalHistory;

use function Pest\Laravel\actingAs;

function assertTestIdCount($response, string $testId, int $expected): void
{
    $html = $response->getContent();
    preg_match(
        '/data-testid="' . preg_quote($testId, '/') . '"[^>]*>\s*(\d+)\s*<\//',
        $html,
        $matches
    );
    expect((int) ($matches[1] ?? -1))
        ->toBe($expected, "Expected data-testid=\"$testId\" to show $expected");
}

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->otherStaff = User::factory()->create(['role' => 'staff']);
});

function createPatientWithVisit(array $patientOverrides, array $visitOverrides): Patient
{
    $patient = Patient::create(array_merge([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'test@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $patientOverrides));

    $visitDefaults = [
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 55,
        'gestational_age' => 20,
        'assessment' => 'Routine checkup',
    ];

    PrenatalVisit::create(array_merge($visitDefaults, $visitOverrides));

    return $patient;
}

// ============ LATEST-VISIT QUERY TESTS ============

it('old HIGH then latest LOW — admin-high-count=0 admin-low-count=1', function () {
    $patient = Patient::create([
        'first_name' => 'Switch',
        'last_name' => 'Test',
        'age' => 28,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'switch@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonth()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Old hypertension'],
        'assessment' => 'Old high',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Now low',
    ]);

    $response = actingAs($this->admin)->get(route('dashboard'));
    $response->assertOk();

    assertTestIdCount($response, 'admin-high-count', 0);
    assertTestIdCount($response, 'admin-low-count', 1);
    $response->assertDontSee('Old hypertension');
});

it('old LOW then latest INCOMPLETE — admin-low-count=0 admin-incomplete-count=1', function () {
    $patient = Patient::create([
        'first_name' => 'IncSwitch',
        'last_name' => 'Test',
        'age' => 28,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'inc@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonth()->toDateString(),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Old low',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => ['Medical History'],
        'assessment' => 'Incomplete',
    ]);

    $response = actingAs($this->admin)->get(route('dashboard'));
    $response->assertOk();

    assertTestIdCount($response, 'admin-low-count', 0);
    assertTestIdCount($response, 'admin-incomplete-count', 1);
});

it('patient with multiple visits contributes to only one count', function () {
    $patient = Patient::create([
        'first_name' => 'Multi',
        'last_name' => 'Visit',
        'age' => 28,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'multi@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonth()->toDateString(),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Low risk',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'assessment' => 'High risk',
    ]);

    $response = actingAs($this->admin)->get(route('dashboard'));
    $response->assertOk();

    assertTestIdCount($response, 'admin-high-count', 1);
    $response->assertDontSee('Low risk');
});

it('soft-deleted newest visit — latest non-deleted visit remains current', function () {
    $patient = Patient::create([
        'first_name' => 'SoftDel',
        'last_name' => 'Visit',
        'age' => 26,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'softdel@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonth()->toDateString(),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Still current after soft-delete',
    ]);

    $deletedVisit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Should be ignored'],
        'assessment' => 'Deleted high',
    ]);

    $deletedVisit->delete();

    $response = actingAs($this->admin)->get(route('dashboard'));
    $response->assertOk();

    $response->assertDontSee('Should be ignored');
});

// ============ STAFF SCOPE TESTS ============

it('staff dashboard shows clinic-wide counts matching admin', function () {
    $assigned = Patient::create([
        'first_name' => 'Assigned',
        'last_name' => 'Staff',
        'age' => 25,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'assigned@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->staff->id,
    ]);

    PrenatalVisit::create([
        'patient_id' => $assigned->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Assigned hypertension'],
        'assessment' => 'Assigned high',
    ]);

    $other = Patient::create([
        'first_name' => 'Other',
        'last_name' => 'Staff',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'other@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->otherStaff->id,
    ]);

    PrenatalVisit::create([
        'patient_id' => $other->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Other hypertension'],
        'assessment' => 'Other high',
    ]);

    $staffResponse = actingAs($this->staff)->get(route('dashboard'));
    $staffResponse->assertOk();
    $adminResponse = actingAs($this->admin)->get(route('dashboard'));
    $adminResponse->assertOk();

    // Dashboard statistics are clinic-wide: staff sees the same HIGH count as admin.
    assertTestIdCount($staffResponse, 'staff-high-count', 2);
    assertTestIdCount($adminResponse, 'admin-high-count', 2);

    // Both patients appear in the priority alerts, regardless of assignment.
    $staffResponse->assertSeeText('Assigned Staff');
    $staffResponse->assertSeeText('Other Staff');
    $staffResponse->assertSeeText('Other hypertension');
});

it('admin dashboard shows patients regardless of staff assignment', function () {
    $patientA = Patient::create([
        'first_name' => 'AdminSees',
        'last_name' => 'A',
        'age' => 25,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'aa@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->staff->id,
    ]);

    PrenatalVisit::create([
        'patient_id' => $patientA->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Admin sees this'],
        'assessment' => 'A high',
    ]);

    $patientB = Patient::create([
        'first_name' => 'AdminSees',
        'last_name' => 'B',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'bb@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->otherStaff->id,
    ]);

    PrenatalVisit::create([
        'patient_id' => $patientB->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Admin also sees this'],
        'assessment' => 'B high',
    ]);

    $response = actingAs($this->admin)->get(route('dashboard'));
    $response->assertOk();
    assertTestIdCount($response, 'admin-high-count', 2);
});

it('My Patients filter still shows only the logged-in staff assigned patients', function () {
    $assigned = Patient::create([
        'first_name' => 'Mine',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'mine@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->staff->id,
    ]);

    Patient::create([
        'first_name' => 'Theirs',
        'last_name' => 'Patient',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'theirs@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->otherStaff->id,
    ]);

    $response = actingAs($this->staff)->get(route('patients.index', ['filter' => 'my']));
    $response->assertOk();
    $response->assertSeeText('Mine Patient');
    $response->assertDontSeeText('Theirs Patient');
});

it('patient records still display the assigned staff owner', function () {
    $patient = Patient::create([
        'first_name' => 'Owner',
        'last_name' => 'Check',
        'age' => 25,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'owner@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'assigned_staff_id' => $this->staff->id,
    ]);

    $response = actingAs($this->staff)->get(route('patients.show', $patient->id));
    $response->assertOk();
    $response->assertSeeText($this->staff->name);
});

// ============ RISK MONITORING EXPLAINABILITY TESTS ============

it('risk monitoring shows RULE_BASED explanations with Clinical Rules label and rule reason', function () {
    createPatientWithVisit([], [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension', 'Diabetes'],
        'assessment' => 'High risk due to hypertension',
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Clinical Rules');
    $response->assertSeeText('Hypertension');
    $response->assertSeeText('Diabetes');
});

it('risk monitoring shows COMPLETENESS with Completeness Check label and missing record', function () {
    createPatientWithVisit([], [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => ['Medical History', 'Ultrasound Record'],
        'assessment' => 'Cannot complete assessment',
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Completeness Check');
    $response->assertSeeText('Medical History');
});

it('risk monitoring shows MACHINE_LEARNING prediction and Valid status', function () {
    createPatientWithVisit([], [
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Low risk assessment',
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Machine Learning');
    $response->assertSeeText('Prediction');
    $response->assertSeeText('Valid');
});

it('risk monitoring shows MACHINE_LEARNING_INVALID safely without raw technical errors', function () {
    createPatientWithVisit([], [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'MACHINE_LEARNING_INVALID',
        'ml_prediction' => null,
        'ml_valid' => false,
        'assessment' => 'ML assessment unavailable',
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('ML Assessment Unavailable');
    $response->assertDontSeeText('Traceback');
    $response->assertDontSeeText('raw_output');
    $response->assertDontSeeText('parsed_output');
});

it('legacy assessments use neutral fallback text', function () {
    createPatientWithVisit([], [
        'risk_level' => 'LOW',
        'decision_source' => null,
        'assessment' => 'Legacy assessment',
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Legacy Assessment');
});

it('ASSESSMENT INCOMPLETE patient is not labelled LOW', function () {
    createPatientWithVisit([], [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => ['Medical History'],
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('INCOMPLETE');
});

it('delivered terminal label remains Delivered', function () {
    createPatientWithVisit([
        'first_name' => 'Delivered',
        'last_name' => 'Mom',
        'status' => 'DELIVERED',
    ], [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'next_visit_date' => now()->subDays(5)->toDateString(),
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Delivered');
});

it('referred terminal label remains Referred', function () {
    createPatientWithVisit([
        'first_name' => 'Referred',
        'last_name' => 'Mom',
        'status' => 'REFERRED',
    ], [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertSeeText('Referred');
});

// ============ FILTER TESTS ============

it('risk level filter excludes non-matching patient names', function () {
    createPatientWithVisit(['first_name' => 'HighOne'], [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
    ]);
    createPatientWithVisit(['first_name' => 'LowOne'], [
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring', [
        'risk_filter' => 'HIGH',
    ]));
    $response->assertOk();
    $response->assertSeeText('HighOne');
    $response->assertDontSeeText('LowOne');
});

it('decision source filter shows only matching decision source', function () {
    createPatientWithVisit(['first_name' => 'RulePat'], [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
    ]);
    createPatientWithVisit(['first_name' => 'MLPat'], [
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring', [
        'decision_source' => 'RULE_BASED',
    ]));
    $response->assertOk();
    $response->assertSeeText('RulePat');
    $response->assertDontSeeText('MLPat');

    $response2 = actingAs($this->staff)->get(route('risk.monitoring', [
        'decision_source' => 'MACHINE_LEARNING',
    ]));
    $response2->assertOk();
    $response2->assertSeeText('MLPat');
    $response2->assertDontSeeText('RulePat');
});

it('risk monitoring filter accepts ASSESSMENT INCOMPLETE', function () {
    createPatientWithVisit([], [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => ['Medical History'],
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring', [
        'risk_filter' => 'ASSESSMENT INCOMPLETE',
    ]));
    $response->assertOk();
});

// ============ RAW FIELDS NEVER RENDERED ============

it('raw fields are never rendered in risk monitoring', function () {
    createPatientWithVisit([], [
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
    ]);

    $response = actingAs($this->staff)->get(route('risk.monitoring'));
    $response->assertOk();
    $response->assertDontSeeText('raw_output');
    $response->assertDontSeeText('parsed_output');
    $response->assertDontSeeText('Traceback');
    $response->assertDontSeeText('predict.py');
    $response->assertDontSeeText('Python');
});

// ============ PRINTABLE REPORT ============

it('printable report includes Decision Source and safety disclaimer', function () {
    $patient = Patient::create([
        'first_name' => 'Print',
        'last_name' => 'Test',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'print@example.com',
        'gravida' => 1,
        'para' => 0,
        'birthdate' => '1999-01-15',
        'civil_status' => 'Single',
        'philhealth_member' => false,
        'lmp' => '2026-01-01',
        'edd' => '2026-10-08',
        'status' => 'ONGOING',
    ]);

    MedicalHistory::create([
        'patient_id' => $patient->id,
        'epilepsy' => false,
        'severe_headache' => false,
        'visual_disturbance' => false,
        'chest_pain' => false,
        'shortness_breath' => false,
        'breast_mass' => false,
        'liver_disease' => false,
        'smoking' => false,
        'allergies' => false,
        'drug_intake' => false,
        'std_history' => false,
        'diabetes' => false,
        'hypertension' => false,
        'asthma' => false,
        'thyroid_disease' => false,
        'heart_disease' => false,
        'anemia' => false,
        'mental_health_condition' => false,
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'assessment' => 'High risk assessment',
    ]);

    $response = actingAs($this->staff)->post(route('patients.download', $patient->id), [
        'format' => 'pdf',
    ]);
    $response->assertOk();
});

it('printable report renders with empty Medical History', function () {
    $patient = Patient::create([
        'first_name' => 'NoHist',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'nohist@example.com',
        'gravida' => 1,
        'para' => 0,
        'birthdate' => '1999-01-15',
        'civil_status' => 'Single',
        'philhealth_member' => false,
        'lmp' => '2026-01-01',
        'edd' => '2026-10-08',
        'status' => 'ONGOING',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Low risk',
    ]);

    $latestVisit = $patient->prenatalVisits->sortByDesc('visit_date')->first();
    $html = view('exports.patient-record', compact('patient', 'latestVisit'))->render();

    expect($html)->toContain('No medical history recorded.');
    expect($html)->toContain('Clinical Decision Summary');
    expect($html)->toContain('Decision Source');
    expect($html)->toContain('Machine Learning');
});

// ============ PATIENT PROFILE STILL WORKS ============

it('existing patient-profile explainability still works', function () {
    $patient = Patient::create([
        'first_name' => 'Explain',
        'last_name' => 'Test',
        'age' => 30,
        'address' => 'Test',
        'contact_number' => '09171234567',
        'email' => 'explain@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);

    MedicalHistory::create([
        'patient_id' => $patient->id,
        'epilepsy' => false,
        'severe_headache' => false,
        'visual_disturbance' => false,
        'chest_pain' => false,
        'shortness_breath' => false,
        'breast_mass' => false,
        'liver_disease' => false,
        'smoking' => false,
        'allergies' => false,
        'drug_intake' => false,
        'std_history' => false,
        'diabetes' => false,
        'hypertension' => false,
        'asthma' => false,
        'thyroid_disease' => false,
        'heart_disease' => false,
        'anemia' => false,
        'mental_health_condition' => false,
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'assessment' => 'High risk assessment',
    ]);

    $response = actingAs($this->staff)->get(route('patients.show', $patient->id));
    $response->assertOk();
    $response->assertSeeText('Risk Assessment');
    $response->assertSeeText('Clinical Rules');
});
