<?php

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Services\BloodPressureAssessmentService;
use App\Services\PatientAssessmentRecalculationService;
use App\Services\RiskAssessmentService;
use App\ValueObjects\AssessmentResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function recalcPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Recalc',
        'last_name' => 'Patient',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));
}

function recalcVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
        'risk_level' => 'ASSESSMENT INCOMPLETE',
    ], $overrides));
}

function recalcCompleteRecords(int $patientId): void
{
    MedicalHistory::create(['patient_id' => $patientId]);
    Ultrasound::create(['patient_id' => $patientId, 'scan_date' => now()->toDateString()]);
    BirthPlan::create(['patient_id' => $patientId]);
}

function recalcAssessmentResult(): AssessmentResult
{
    return new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'Test assessment',
        recommendation: 'Test recommendation',
        reasons: ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addWeek(),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Diabetes'],
        ml_prediction: null,
        ml_valid: false,
        urgency: 'PROMPT_CLINICAL_REVIEW',
        bp_assessment: [
            'reason_code' => 'BP-N',
            'verification_status' => BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED,
        ],
    );
}

test('recalculates ASSESSMENT INCOMPLETE visits once all required records exist', function () {
    $patient = recalcPatient();
    $visitA = recalcVisit($patient->id);
    $visitB = recalcVisit($patient->id);

    recalcCompleteRecords($patient->id);

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->times(2)->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $visitA->refresh();
    $visitB->refresh();

    expect($visitA->risk_level)->toBe('HIGH');
    expect($visitA->assessment)->toBe('Test assessment');
    expect($visitA->risk_reasons)->toBe(['Diabetes']);
    expect($visitA->decision_source)->toBe('RULE_BASED');
    expect($visitA->bp_assessment['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED);
    expect($visitB->risk_level)->toBe('HIGH');
});

test('never recalculates HIGH visits', function () {
    $patient = recalcPatient();
    $incomplete = recalcVisit($patient->id);
    $high = recalcVisit($patient->id, ['risk_level' => 'HIGH']);

    recalcCompleteRecords($patient->id);

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->times(1)->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $incomplete->refresh();
    $high->refresh();

    expect($incomplete->risk_level)->toBe('HIGH');
    expect($high->risk_level)->toBe('HIGH');
});

test('never recalculates LOW visits', function () {
    $patient = recalcPatient();
    $incomplete = recalcVisit($patient->id);
    $low = recalcVisit($patient->id, ['risk_level' => 'LOW']);

    recalcCompleteRecords($patient->id);

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->times(1)->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $low->refresh();
    $incomplete->refresh();

    expect($incomplete->risk_level)->toBe('HIGH');
    expect($low->risk_level)->toBe('LOW');
});

test('never recalculates visits of delivered patients', function () {
    $patient = recalcPatient(['status' => 'DELIVERED']);
    $visit = recalcVisit($patient->id);

    recalcCompleteRecords($patient->id);

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->never();

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $visit->refresh();

    expect($visit->risk_level)->toBe('ASSESSMENT INCOMPLETE');
});

test('does not recalculate when any required record is missing', function () {
    $patient = recalcPatient();
    recalcVisit($patient->id);

    MedicalHistory::create(['patient_id' => $patient->id]);
    // No Ultrasound, no BirthPlan.

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->never();

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit->risk_level)->toBe('ASSESSMENT INCOMPLETE');
});

test('does nothing when the patient does not exist', function () {
    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->never();

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits(999999);

    expect(PrenatalVisit::count())->toBe(0);
});

test('preserves the repeat BP pair and verification metadata when recalculating', function () {
    $patient = recalcPatient();
    recalcVisit($patient->id, [
        'repeat_bp_sys' => 120,
        'repeat_bp_dia' => 85,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
        'bp_assessment' => [
            'verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
            'verification_note' => 'Repeat taken after 15 minutes',
        ],
    ]);

    recalcCompleteRecords($patient->id);

    $captured = null;

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')
        ->withArgs(function (Patient $patient, array $inputs, ?array $repeatBpInputs, ?string $bpVerificationStatus, ?string $bpVerificationNote) use (&$captured) {
            $captured = [
                'repeatBpInputs' => $repeatBpInputs,
                'bpVerificationStatus' => $bpVerificationStatus,
                'bpVerificationNote' => $bpVerificationNote,
            ];

            return true;
        })
        ->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    expect($captured['repeatBpInputs'])->toBe(['bp_sys' => 120, 'bp_dia' => 85]);
    expect($captured['bpVerificationStatus'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
    expect($captured['bpVerificationNote'])->toBe('Repeat taken after 15 minutes');
});

test('preserves an existing next_visit_date when recalculating', function () {
    $patient = recalcPatient();
    $visit = recalcVisit($patient->id, ['next_visit_date' => '2026-08-15']);

    recalcCompleteRecords($patient->id);

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')->times(1)->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    $visit->refresh();

    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->next_visit_date->toDateString())->toBe('2026-08-15');
});

test('passes the visit checkboxes, not medical history fields, as the CDSS inputs', function () {
    $patient = recalcPatient();
    recalcVisit($patient->id, ['diabetes' => 0, 'anemia' => 0]);

    // The medical history claims diabetes and anemia, but the CDSS inputs
    // must come from the prenatal visit assessment, never from medical history.
    MedicalHistory::create([
        'patient_id' => $patient->id,
        'diabetes' => 1,
        'anemia' => 1,
        'severe_headache' => 1,
        'visual_disturbance' => 1,
        'chest_pain' => 1,
        'shortness_breath' => 1,
    ]);
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);
    BirthPlan::create(['patient_id' => $patient->id]);

    $capturedInputs = null;

    $mock = Mockery::mock(RiskAssessmentService::class);
    $mock->shouldReceive('assess')
        ->withArgs(function (Patient $patient, array $inputs) use (&$capturedInputs) {
            $capturedInputs = $inputs;

            return true;
        })
        ->andReturn(recalcAssessmentResult());

    $service = new PatientAssessmentRecalculationService($mock);
    $service->recalculateIncompleteVisits($patient->id);

    expect($capturedInputs)->not->toBeNull();
    expect($capturedInputs['diabetes'])->toBeFalse();
    expect($capturedInputs['anemia'])->toBeFalse();
    expect($capturedInputs)->not->toHaveKey('severe_headache');
    expect($capturedInputs)->not->toHaveKey('visual_disturbance');
    expect($capturedInputs)->not->toHaveKey('chest_pain');
    expect($capturedInputs)->not->toHaveKey('shortness_breath');
});
