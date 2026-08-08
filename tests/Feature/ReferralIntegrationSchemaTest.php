<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralAssessmentSnapshotService;
use Illuminate\Support\Facades\Schema;

function snapshotPatient(string $firstName = 'Maria'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function snapshotVisit(array $overrides = []): PrenatalVisit
{
    $patient = snapshotPatient();

    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 28,
        'risk_level' => 'HIGH',
        'assessment' => 'High risk detected',
        'recommendation' => 'Refer to specialist',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['CS-01'],
        'factor_evidence' => [
            [
                'code' => 'CS-01',
                'label' => 'Previous cesarean section',
                'category' => 'OBSTETRIC_HISTORY',
                'source_type' => 'PRENATAL_VISIT',
                'source_fields' => ['previous_cs'],
                'observed_value' => 'Yes',
                'threshold_or_rule' => 'Previous cesarean section present',
                'decision_effect' => 'HIGH',
            ],
        ],
        'bp_assessment' => ['status' => 'NORMAL', 'reason_code' => 'N', 'urgency' => null],
        'assessment_metadata' => [
            'context' => ['assessment_date' => now()->toDateString()],
            'interaction_evidence' => [
                ['code' => 'INT-CS-PRES', 'label' => 'Previous cesarean with abnormal presentation', 'required_factor_codes' => ['CS-01', 'US-P01']],
            ],
            'versions' => ['assessment_engine' => '1.0.0', 'clinical_rules' => '1.1.0', 'context' => 1],
            'assessed_at' => '2026-08-09T10:00:00+00:00',
        ],
    ], $overrides));
}

it('creates required referral integration columns', function () {
    expect(Schema::hasColumn('referrals', 'prenatal_visit_id'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'assessment_snapshot'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_recorded_at'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_recorded_by'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'refusal_notes'))->toBeTrue();
    expect(Schema::hasColumn('referrals', 'waiver_signed'))->toBeTrue();
});

it('supports the Refused status after migration', function () {
    $referral = Referral::create([
        'patient_id' => snapshotPatient()->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Test',
        'referral_date' => now()->toDateString(),
        'status' => 'Refused',
    ]);

    expect($referral->status)->toBe('Refused');
});

it('maps waiver_signed as a boolean fillable', function () {
    $referral = Referral::create([
        'patient_id' => snapshotPatient()->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Test',
        'referral_date' => now()->toDateString(),
        'waiver_signed' => true,
    ]);

    expect($referral->waiver_signed)->toBeTrue();
});

it('allows a legacy referral with all new fields null', function () {
    $referral = Referral::create([
        'patient_id' => snapshotPatient()->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Legacy manual referral',
        'referral_date' => now()->toDateString(),
        'status' => 'Cancelled',
    ]);

    expect($referral->prenatal_visit_id)->toBeNull();
    expect($referral->assessment_snapshot)->toBeNull();
    expect($referral->refusal_recorded_at)->toBeNull();
    expect($referral->refusal_recorded_by)->toBeNull();
    expect($referral->refusal_notes)->toBeNull();
    expect($referral->waiver_signed)->toBeFalsy();
    expect($referral->status)->toBe('Cancelled');
});

it('casts assessment_snapshot to an array when provided', function () {
    $referral = Referral::create([
        'patient_id' => snapshotPatient()->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Test',
        'referral_date' => now()->toDateString(),
        'assessment_snapshot' => ['risk_level' => 'HIGH', 'schema_version' => '1.0.0'],
    ]);

    expect(is_array($referral->assessment_snapshot))->toBeTrue();
    expect($referral->assessment_snapshot['risk_level'])->toBe('HIGH');
});

it('builds the Referral prenatalVisit relationship', function () {
    $visit = snapshotVisit();
    $referral = Referral::create([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Linked',
        'referral_date' => now()->toDateString(),
    ]);

    expect($referral->prenatalVisit)->not->toBeNull();
    expect($referral->prenatalVisit->id)->toBe($visit->id);
});

it('builds the PrenatalVisit referrals relationship', function () {
    $visit = snapshotVisit();
    $referral = Referral::create([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'created_by' => User::factory()->create()->id,
        'referred_to' => 'Hospital',
        'reason' => 'Linked',
        'referral_date' => now()->toDateString(),
    ]);

    expect($visit->referrals)->toHaveCount(1);
    expect($visit->referrals->first()->id)->toBe($referral->id);
});

it('builds the refusalRecordedBy relationship', function () {
    $user = User::factory()->create();
    $referral = Referral::create([
        'patient_id' => snapshotPatient()->id,
        'created_by' => $user->id,
        'refusal_recorded_by' => $user->id,
        'refusal_recorded_at' => now(),
        'refusal_notes' => 'Patient declined',
        'referred_to' => 'Hospital',
        'reason' => 'Test',
        'referral_date' => now()->toDateString(),
        'status' => 'Refused',
    ]);

    expect($referral->refusalRecordedBy)->not->toBeNull();
    expect($referral->refusalRecordedBy->id)->toBe($user->id);
});

it('builds a snapshot with only approved keys from persisted values', function () {
    $visit = snapshotVisit();

    $snapshot = app(ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);

    expect($snapshot)->not->toBeNull();
    expect(array_keys($snapshot))->toBe([
        'schema_version', 'prenatal_visit_id', 'visit_date',
        'risk_level', 'decision_source', 'urgency', 'assessment', 'recommendation',
        'rule_reasons', 'factor_evidence', 'interaction_evidence', 'bp_assessment',
        'assessment_date', 'assessed_at', 'versions',
    ]);

    expect($snapshot['risk_level'])->toBe('HIGH');
    expect($snapshot['decision_source'])->toBe('RULE_BASED');
    expect($snapshot['prenatal_visit_id'])->toBe($visit->id);
    expect($snapshot['versions']['clinical_rules'])->toBe('1.1.0');
});

it('copies interaction evidence from persisted metadata', function () {
    $visit = snapshotVisit();
    $snapshot = app(ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);

    expect($snapshot['interaction_evidence'])->toHaveCount(1);
    expect($snapshot['interaction_evidence'][0]['code'])->toBe('INT-CS-PRES');
});

it('copies versions and assessed_at from persisted metadata', function () {
    $visit = snapshotVisit();
    $snapshot = app(ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);

    expect($snapshot['assessed_at'])->toBe($visit->assessment_metadata['assessed_at']);
    expect($snapshot['versions'])->toBe($visit->assessment_metadata['versions']);
});

it('excludes patient PII from the snapshot', function () {
    $patient = snapshotPatient('Jacinta');
    $visit = snapshotVisit(['patient_id' => $patient->id]);

    $snapshot = app(ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);

    $json = json_encode($snapshot);
    expect($json)->not->toContain('Jacinta');
    expect($json)->not->toContain('Test');
    expect($json)->not->toContain($patient->address);
    expect($json)->not->toContain($patient->contact_number);
});

it('returns null when the visit has no persisted assessment', function () {
    $visit = PrenatalVisit::create([
        'patient_id' => snapshotPatient()->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => null,
        'assessment_metadata' => null,
    ]);

    expect(app(ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit))->toBeNull();
});