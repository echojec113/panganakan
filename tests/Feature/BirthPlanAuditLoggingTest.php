<?php

use App\Models\AuditLog;
use App\Models\BirthPlan;
use App\Models\Patient;
use App\Models\User;

function birthPlanAuditPayload(int $patientId, array $overrides = []): array
{
    return array_merge([
        'patient_id' => $patientId,
        'planned_visits' => 8,
        'deliver_in_clinic' => 1,
        'delivery_location' => 'Clinic',
        'transportation' => 'Tricycle',
        'transport_cost' => 100,
        'payment_method' => 'Cash',
        'saving_started' => 1,
        'birth_companion' => 'Partner',
        'caregiver_home' => 'Mother',
        'plan_more_children' => 0,
        'number_more_children' => 0,
        'knows_fp_method' => 1,
        'used_fp_before' => 0,
    ], $overrides);
}

it('audits birth-plan creation, update, and deletion once with record context', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $patient = Patient::create([
        'first_name' => 'Plan',
        'last_name' => 'Audit',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'status' => 'ONGOING',
    ]);

    $this->actingAs($staff)
        ->post(route('birth-plans.store'), birthPlanAuditPayload($patient->id))
        ->assertRedirect(route('patients.show', $patient->id));

    $birthPlan = BirthPlan::sole();
    $createLog = AuditLog::where('action', 'CREATE')
        ->where('module', 'BIRTH_PLAN')
        ->where('record_id', $birthPlan->id)
        ->sole();
    expect($createLog->user_id)->toBe($staff->id)
        ->and($createLog->record_type)->toBe('BIRTH_PLAN')
        ->and($createLog->patient_id)->toBe($patient->id);

    $this->actingAs($staff)
        ->put(route('birth-plans.update', $birthPlan->id), birthPlanAuditPayload($patient->id, [
            'transport_cost' => 150,
        ]))
        ->assertRedirect(route('patients.show', $patient->id));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'UPDATE',
        'module' => 'BIRTH_PLAN',
        'record_type' => 'BIRTH_PLAN',
        'record_id' => $birthPlan->id,
        'user_id' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('birth-plans.destroy', $birthPlan->id))
        ->assertRedirect(route('patients.show', $patient->id));

    expect(AuditLog::where('module', 'BIRTH_PLAN')
        ->where('record_id', $birthPlan->id)
        ->orderBy('id')
        ->pluck('action')
        ->all())->toBe(['CREATE', 'UPDATE', 'DELETE']);
});
