<?php

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Services\AssessmentContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function builderPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'age' => 28,
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

test('latest ultrasound uses scan_date desc then created_at desc then id desc', function () {
    $patient = builderPatient();

    $older = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->subDays(10)->toDateString()]);
    $newer = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->subDays(2)->toDateString()]);

    $builder = new AssessmentContextBuilder;

    expect($builder->latestUltrasound($patient->id)->id)->toBe($newer->id);
    expect($builder->latestUltrasound($patient->id)->id)->not->toBe($older->id);
});

test('buildForPatient persists the exact ultrasound record selected', function () {
    $patient = builderPatient();
    $latest = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->subDays(2)->toDateString()]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05');

    expect($context->ultrasound_id)->toBe((int) $latest->id);
    expect($context->ultrasound_present)->toBeTrue();
});

test('buildForPatient captures the exact ultrasound id, date, and evaluated values', function () {
    $patient = builderPatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'BREECH',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Normal',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05');

    expect($context->ultrasound_id)->toBe((int) $ultrasound->id);
    expect($context->ultrasound_date)->toBe(now()->subDays(2)->toDateString());
    expect($context->ultrasound_inputs)->toBe([
        'presentation' => 'BREECH',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Normal',
    ]);
});

test('normal non-triggering ultrasound values are preserved in the context', function () {
    $patient = builderPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05');

    expect($context->ultrasound_inputs)->toBe([
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
});

test('a later model change does not mutate an already-built context', function () {
    $patient = builderPatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);

    $builder = new AssessmentContextBuilder;
    $context = $builder->buildForPatient($patient, null, [], '2026-08-05');

    $ultrasound->update([
        'presentation' => 'BREECH',
        'amniotic_fluid' => 'LOW',
        'fetal_heartbeat' => 'ABSENT',
    ]);

    expect($context->ultrasound_inputs)->toBe([
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
    expect($context->ultrasound_id)->toBe((int) $ultrasound->id);
});

test('context serialization carries only scalars and arrays, never an eloquent model', function () {
    $patient = builderPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05');

    $json = json_encode($context->toArray());

    expect($json)->toBeString();
    expect($json)->not->toContain('Ultrasound');
    expect($json)->not->toContain('Eloquent');
    expect($json)->not->toContain('first_name');
    expect($json)->not->toContain('last_name');
    expect($json)->not->toContain('email');
    expect($json)->not->toContain('address');
});

test('ultrasound inputs accept a pre-selected record so no latest reselection is needed', function () {
    $patient = builderPatient();
    $older = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->subDays(10)->toDateString()]);
    $newer = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->subDays(2)->toDateString()]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05', null, $older);

    expect($context->ultrasound_id)->toBe((int) $older->id);
    expect($context->ultrasound_id)->not->toBe((int) $newer->id);
});

test('buildForPatient reports missing records as absent', function () {
    $patient = builderPatient();

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-05');

    expect($context->ultrasound_present)->toBeFalse();
    expect($context->medical_history_exists)->toBeFalse();
    expect($context->birth_plan_exists)->toBeFalse();
});

test('buildForPatient passes through the assessment date and visit', function () {
    $patient = builderPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => '2026-08-04',
        'bp_sys' => 120,
        'bp_dia' => 80,
        'gestational_age' => 20,
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, $visit, [], '2026-08-04');

    expect($context->assessment_date)->toBe('2026-08-04');
    expect($context->prenatal_visit_id)->toBe((int) $visit->id);
    expect($context->prenatal_visit_date)->toBe('2026-08-04');
});

test('duplicate counts report active medical history and birth plan records', function () {
    $patient = builderPatient();

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $builder = new AssessmentContextBuilder;

    expect($builder->activeMedicalHistoryCount($patient->id))->toBe(2);
    expect($builder->activeBirthPlanCount($patient->id))->toBe(2);
});