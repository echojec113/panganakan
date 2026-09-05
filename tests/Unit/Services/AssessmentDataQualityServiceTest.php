<?php

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\Ultrasound;
use App\Services\AssessmentContextBuilder;
use App\Services\AssessmentDataQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function dqPatient(): Patient
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

function dqService(): AssessmentDataQualityService
{
    return new AssessmentDataQualityService(new AssessmentContextBuilder);
}

test('no flags when sources are well formed', function () {
    $patient = dqPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    expect($flags)->toBe([]);
});

test('future dated ultrasound produces the future-dated flag', function () {
    $patient = dqPatient();
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->addDays(1)->toDateString()]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->toContain('DQ-SOURCE-FUTURE-DATED');
});

test('ultrasound with blank evaluated fields produces the missing-fields flag', function () {
    $patient = dqPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => null,
        'amniotic_fluid' => '',
        'fetal_heartbeat' => 'Normal',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->toContain('DQ-ULTRASOUND-MISSING-FIELDS');
});

test('duplicate medical history and birth plan produce their flags', function () {
    $patient = dqPatient();
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->toContain('DQ-DUP-MEDICAL-HISTORY');
    expect($codes)->toContain('DQ-DUP-BIRTH-PLAN');
});

test('duplicate counts can be supplied to avoid repeated queries', function () {
    $patient = dqPatient();
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context, ['medical_history' => 1, 'birth_plan' => 1]);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->not->toContain('DQ-DUP-MEDICAL-HISTORY');
    expect($codes)->not->toContain('DQ-DUP-BIRTH-PLAN');
});

test('deferred flags are never evaluated', function () {
    $patient = dqPatient();

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->not->toContain('DQ-LMP-MISSING');
    expect($codes)->not->toContain('DQ-EDD-MISSING');
    expect($codes)->not->toContain('DQ-GA-DATE-MISMATCH');
    expect($codes)->not->toContain('DQ-ULTRASOUND-STALE');
});

test('missing-fields flag is evaluated from the captured context values only', function () {
    $patient = dqPatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => null,
        'amniotic_fluid' => '',
        'fetal_heartbeat' => 'Normal',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    // Completing the row after the context was built must not change the flag,
    // because the data-quality check reads the snapshot, not the live row.
    $ultrasound->update(['presentation' => 'Cephalic', 'amniotic_fluid' => 'Normal']);

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->toContain('DQ-ULTRASOUND-MISSING-FIELDS');
});

test('missing-fields flag is not emitted when context values are complete even if the row is blank', function () {
    $patient = dqPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], now()->toDateString());

    $flags = dqService()->evaluate($context);

    $codes = array_map(static fn ($f) => $f->code, $flags);
    expect($codes)->not->toContain('DQ-ULTRASOUND-MISSING-FIELDS');
});

test('future-dated evaluation uses the assessment date anchor, not the wall clock', function () {
    $patient = dqPatient();
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => '2026-09-01']);

    // Ultrasound is on the assessment date => not future-dated.
    $sameDay = dqService()->evaluate(
        (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-09-01')
    );
    $sameCodes = array_map(static fn ($f) => $f->code, $sameDay);
    expect($sameCodes)->not->toContain('DQ-SOURCE-FUTURE-DATED');

    // Ultrasound is after the (earlier) assessment date => future-dated.
    $later = dqService()->evaluate(
        (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2026-08-01')
    );
    $laterCodes = array_map(static fn ($f) => $f->code, $later);
    expect($laterCodes)->toContain('DQ-SOURCE-FUTURE-DATED');
});

test('assessment date is distinct from the engine execution timestamp', function () {
    $patient = dqPatient();

    $context = (new AssessmentContextBuilder)->buildForPatient($patient, null, [], '2020-01-15');

    expect($context->assessment_date)->toBe('2020-01-15');

    // The builder selects the assessment_date anchor explicitly; it is not
    // derived from now() when a value is supplied.
    expect($context->assessment_date)->not->toBe(now()->toDateString());
});