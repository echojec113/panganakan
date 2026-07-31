<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\BloodPressureAssessmentService;

function makeSprint10Patient(): Patient
{
    return Patient::create([
        'first_name' => 'Cora',
        'last_name' => 'Test',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

function sprint10VisitPayload(int $patientId, array $overrides = []): array
{
    return array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
    ], $overrides);
}

it('rejects unable to repeat status without a note on store', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $response = $this->actingAs($user)->post(route('prenatal-visits.store'), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 80,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        'bp_verification_note' => '   ',
    ]));

    $response->assertSessionHasErrors(['bp_verification_note']);
    expect(PrenatalVisit::where('patient_id', $patient->id)->count())->toBe(0);
});

it('rejects unable to repeat status without a note on update', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 140,
        'bp_dia' => 80,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 80,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        'bp_verification_note' => '',
    ]));

    $response->assertSessionHasErrors(['bp_verification_note']);
});

it('stores bp-urg with urgent clinical review and derived status', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $response = $this->actingAs($user)->post(route('prenatal-visits.store'), sprint10VisitPayload($patient->id, [
        'bp_sys' => 165,
        'bp_dia' => 110,
    ]));

    $response->assertRedirect(route('prenatal-visits.index'));

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($visit->bp_assessment['reason_code'])->toBe('BP-URG');
    expect($visit->bp_verification_status)->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

it('stores bp-h with pending repeat and prompt urgency', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $response = $this->actingAs($user)->post(route('prenatal-visits.store'), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 80,
    ]));

    $response->assertRedirect(route('prenatal-visits.index'));

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();
    expect($visit->bp_assessment['reason_code'])->toBe('BP-H');
    expect($visit->bp_verification_status)->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
    expect($visit->urgency)->toBe(BloodPressureAssessmentService::URGENCY_PROMPT);
});

it('clears stale repeat pair and derives status when initial bp edited', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 140,
        'bp_dia' => 85,
        'repeat_bp_sys' => 145,
        'repeat_bp_dia' => 88,
        'repeat_bp_recorded_at' => now(),
        'repeat_bp_recorded_by' => $user->id,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 120,
        'bp_dia' => 80,
        'repeat_bp_sys' => 145,
        'repeat_bp_dia' => 88,
    ]));

    $response->assertRedirect();

    $visit->refresh();
    expect($visit->bp_sys)->toBe(120);
    expect($visit->repeat_bp_sys)->toBeNull();
    expect($visit->repeat_bp_dia)->toBeNull();
    expect($visit->bp_verification_status)->toBe(BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED);
    expect($visit->urgency)->toBeNull();
});

it('rejects a forged repeat completed status at validation', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 140,
        'bp_dia' => 80,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 80,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
    ]));

    $response->assertSessionHasErrors(['bp_verification_status']);

    $visit->refresh();
    expect($visit->bp_verification_status)->toBeNull();
});

it('derives pending repeat status on update when no status is submitted', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 140,
        'bp_dia' => 80,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 80,
    ]));

    $response->assertRedirect();

    $visit->refresh();
    expect($visit->bp_verification_status)->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
    expect($visit->repeat_bp_sys)->toBeNull();
});

it('rejects patient reassignment on update', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patientA = makeSprint10Patient();
    $patientB = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patientA->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patientB->id, [
        'bp_sys' => 120,
        'bp_dia' => 80,
    ]));

    $response->assertSessionHasErrors(['patient_id']);

    $visit->refresh();
    expect($visit->patient_id)->toBe($patientA->id);
});

it('preserves stored severe repeat bp in assessment when repeat fields are omitted', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'repeat_bp_sys' => 170,
        'repeat_bp_dia' => 115,
        'repeat_bp_recorded_at' => now(),
        'repeat_bp_recorded_by' => $user->id,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 120,
        'bp_dia' => 80,
        'notes' => 'Updated note only',
    ]));

    $response->assertRedirect();

    $visit->refresh();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($visit->bp_assessment['reason_code'])->toBe('BP-URG');
    expect($visit->repeat_bp_sys)->toBe(170);
    expect($visit->repeat_bp_dia)->toBe(115);
    expect($visit->notes)->toBe('Updated note only');
});

it('preserves repeat bp recorded metadata and avoids a log when the pair is unchanged', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = makeSprint10Patient();
    $recordedAt = now()->subDay();

    $visit = PrenatalVisit::create(sprint10VisitPayload($patient->id, [
        'visit_date' => now()->subDay()->toDateString(),
        'bp_sys' => 140,
        'bp_dia' => 85,
        'repeat_bp_sys' => 145,
        'repeat_bp_dia' => 88,
        'repeat_bp_recorded_at' => $recordedAt,
        'repeat_bp_recorded_by' => $user->id,
        'bp_verification_status' => BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED,
    ]));

    $response = $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), sprint10VisitPayload($patient->id, [
        'bp_sys' => 140,
        'bp_dia' => 85,
        'repeat_bp_sys' => 145,
        'repeat_bp_dia' => 88,
        'notes' => 'No BP change',
    ]));

    $response->assertRedirect();

    $visit->refresh();
    expect($visit->repeat_bp_recorded_at->toDateTimeString())->toBe($recordedAt->toDateTimeString());
    expect($visit->repeat_bp_recorded_by)->toBe($user->id);

    expect(\App\Models\AuditLog::where('action', 'BP_REPEAT_RECORDED')->count())->toBe(0);
});
