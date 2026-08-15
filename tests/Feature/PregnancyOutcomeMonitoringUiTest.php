<?php

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\Referral;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\PregnancyOutcomeVocabulary;

function monitoringUiUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function monitoringUiPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'birthdate' => '1996-05-01',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'edd' => now()->subDays(15)->toDateString(),
    ], $overrides));
}

function monitoringUiConfirmedDelivery(Patient $patient): PregnancyOutcome
{
    return PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'confirmed_at' => now()->subDays(2),
    ]);
}

it('AB — a staff member sees the monitoring page with the new title', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertOk();
    $response->assertSee('Pregnancy Outcome Monitoring');
    $response->assertSee('Maria Reyes');
});

it('AC — the sidebar label was renamed to Pregnancy Outcome Monitoring', function () {
    $user = monitoringUiUser();

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Pregnancy Outcome Monitoring');
    $response->assertDontSee('Delivered Patients');
});

it('AD — Confirmation Required rows use the friendly label and count', function () {
    $user = monitoringUiUser();
    monitoringUiPatient(['first_name' => 'Ana', 'last_name' => 'Cruz']);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Outcome Confirmation Required');
    $response->assertSee('Confirmation Required');
});

it('AE — a fresh still-pregnant observation renders the friendly state', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => now()->subDays(2),
        'follow_up_recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Still Pregnant — Confirmed');
    $response->assertSee('Still Pregnant');
});

it('AF — a fresh unable-to-contact observation renders the friendly state', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
        'follow_up_recorded_at' => now()->subDays(1),
        'follow_up_recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Unable to Contact');
});

it('AG — a confirmed delivery renders as Confirmed Delivery with provenance labels', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    monitoringUiConfirmedDelivery($patient);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Confirmed Delivery');
    $response->assertSee('This Clinic');
    $response->assertSee('Clinic Record');
});

it('AH — a legacy DELIVERED record stays valid with historical wording and no fabricated provenance', function () {
    $user = monitoringUiUser();
    monitoringUiPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subMonths(2)->toDateString()]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Historical Delivered Record');
    $response->assertDontSee('This Clinic');
    $response->assertDontSee('Clinic Record');
});

it('AI — a legacy REFERRED record renders historical wording', function () {
    $user = monitoringUiUser();
    monitoringUiPatient(['status' => 'REFERRED']);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Legacy Referred Record');
});

it('AJ — raw internal enum strings never leak into the page', function () {
    $user = monitoringUiUser();
    monitoringUiPatient();
    $delivered = monitoringUiPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    monitoringUiConfirmedDelivery($delivered);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertOk();
    foreach ([
        'CONFIRMATION_REQUIRED',
        'STILL_PREGNANT_CONFIRMED',
        'UNABLE_TO_CONTACT',
        'RESOLVED',
        'LEGACY_DELIVERED',
        'LEGACY_REFERRED',
        'THIS_CLINIC',
        'CLINIC_RECORD',
        'ONGOING',
        'DELIVERED',
    ] as $raw) {
        $response->assertDontSee($raw);
    }
});

it('AK — follow-up action buttons appear only for eligible ONGOING rows', function () {
    $user = monitoringUiUser();
    $eligible = monitoringUiPatient(['first_name' => 'Bianca', 'last_name' => 'Ramos']);
    $resolved = monitoringUiPatient(['first_name' => 'Carla', 'last_name' => 'Diaz', 'status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    monitoringUiConfirmedDelivery($resolved);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Confirm Still Pregnant');
    $response->assertSee('Unable to Contact');

    // The eligible ONGOING row owns the follow-up actions.
    $html = $response->getContent();
    expect(str_contains($html, route('pregnancy-outcomes.still-pregnant', $eligible->id)))->toBeTrue();
    // The delivered row must not carry the follow-up action forms.
    expect(str_contains($html, route('pregnancy-outcomes.still-pregnant', $resolved->id)))->toBeFalse();
    expect(str_contains($html, route('pregnancy-outcomes.unable-to-contact', $resolved->id)))->toBeFalse();
});

it('AL — an admin can read the page but cannot mutate (403 on follow-up POST)', function () {
    $admin = monitoringUiUser('admin');
    $patient = monitoringUiPatient();

    $read = $this->actingAs($admin)->get(route('pregnancy-outcomes.index'));
    $read->assertOk();

    $write = $this->actingAs($admin)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $write->assertForbidden();

    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AM — both desktop table and mobile card regions render usable data', function () {
    $user = monitoringUiUser();
    monitoringUiPatient(['first_name' => 'Diana', 'last_name' => 'Lopez']);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Diana Lopez');
    $response->assertSee('<table', false);
    $response->assertSee('Showing 1 to');
});

it('AN — pagination stays at 15 rows per page', function () {
    $user = monitoringUiUser();
    for ($i = 1; $i <= 20; $i++) {
        monitoringUiPatient(['first_name' => 'Patient', 'last_name' => 'Number' . $i]);
    }

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertOk();
    $response->assertViewHas('paginator', fn ($paginator) => $paginator->total() === 20 && $paginator->perPage() === 15);
});

it('AO — search narrows the queue by name', function () {
    $user = monitoringUiUser();
    monitoringUiPatient(['first_name' => 'Elena', 'last_name' => 'Garcia']);
    monitoringUiPatient(['first_name' => 'Faye', 'last_name' => 'Ocampo']);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index', ['search' => 'Garcia']));

    $response->assertSee('Elena Garcia');
    $response->assertDontSee('Faye Ocampo');
});

it('AP — the state filter narrows the queue by derived state using a friendly slug', function () {
    $user = monitoringUiUser();
    $confirm = monitoringUiPatient(['first_name' => 'Gina', 'last_name' => 'Aquino']);
    $fresh = monitoringUiPatient(['first_name' => 'Helen', 'last_name' => 'Bautista']);
    PregnancyOutcome::create([
        'patient_id' => $fresh->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => now()->subDays(1),
        'follow_up_recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index', ['state' => 'confirmation-required']));

    $response->assertSee('Gina Aquino');
    $response->assertDontSee('Helen Bautista');
});

it('AQ — a delivery recorded through the existing 17C flow derives RESOLVED on the page', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();

    $deliver = $this->actingAs($user)->post(route('patients.deliver', $patient->id), [
        'delivery_date' => now()->toDateString(),
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'outcome_notes' => 'Confirmed delivery.',
        'babies' => [[
            'first_name' => 'Baby',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => now()->toDateString(),
            'time_of_birth' => '09:30',
        ]],
    ]);
    $deliver->assertSessionHasNoErrors();

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Confirmed Delivery');
    expect($patient->refresh()->pregnancyOutcome->hasConfirmedOutcome())->toBeTrue();
});

it('AR — recording a confirmed delivery clears pending follow-up state', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => now()->subDays(1),
        'follow_up_recorded_by' => $user->id,
    ]);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), [
        'delivery_date' => now()->toDateString(),
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'babies' => [[
            'date_of_birth' => now()->toDateString(),
            'time_of_birth' => '09:30',
        ]],
    ]);

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_status)->toBeNull();
    expect($outcome->hasConfirmedOutcome())->toBeTrue();
});

it('AS — a pending referral and a confirmation-required state coexist independently', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();
    Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'City Health Office',
        'reason' => 'Regular check-up referral',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Outcome Confirmation Required');
    expect($patient->refresh()->hasActiveReferral())->toBeTrue();
    expect($patient->status)->toBe('ONGOING');
});

it('AT — overdue prenatal visits do not change the derived monitoring state', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient();
    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonths(2)->toDateString(),
        'next_visit_date' => now()->subMonth()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['PH'],
    ]);

    $service = app(PregnancyOutcomeMonitoringService::class);
    expect($service->deriveState($patient))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED);
});

it('AV — a FUTURE EDD row renders NOT_YET_DUE and no follow-up buttons', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['first_name' => 'Lily', 'last_name' => 'Medrano', 'edd' => now()->addDays(10)->toDateString()]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $response->assertDontSee(route('pregnancy-outcomes.unable-to-contact', $patient->id));
});

it('AW — an EDD TODAY row renders NOT_YET_DUE and no follow-up buttons', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['first_name' => 'Mara', 'last_name' => 'Nacino', 'edd' => now()->toDateString()]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $response->assertDontSee(route('pregnancy-outcomes.unable-to-contact', $patient->id));
});

it('AX — a NULL EDD row renders NOT_YET_DUE and no follow-up buttons', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['first_name' => 'Nina', 'last_name' => 'Ocampo', 'edd' => null]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $response->assertDontSee(route('pregnancy-outcomes.unable-to-contact', $patient->id));
});

it('AY — a PASSED EDD ONGOING row renders the follow-up action buttons', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(); // default edd already passed (now - 15 days)

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Confirm Still Pregnant');
    $response->assertSee(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $response->assertSee(route('pregnancy-outcomes.unable-to-contact', $patient->id));
    expect($patient->refresh()->status)->toBe('ONGOING');
});

it('AZ — the Unable to Contact summary uses neutral unsuccessful-attempt wording', function () {
    $user = monitoringUiUser();

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Recent follow-up attempt was unsuccessful.');
    $response->assertDontSee('Reached near EDD but not contacted recently');
});

it('AU — Start New Pregnancy still works and does not inherit follow-up/outcome state', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['gravida' => 2, 'para' => 1]);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), [
        'delivery_date' => now()->toDateString(),
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'babies' => [[
            'date_of_birth' => now()->toDateString(),
            'time_of_birth' => '09:30',
        ]],
    ]);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => now()->subMonths(4)->toDateString(),
        'edd' => now()->addMonths(5)->toDateString(),
        'address' => 'New address',
        'contact_number' => '09181234567',
    ]);
    $response->assertSessionHasNoErrors();

    $newPregnancy = Patient::where('status', 'ONGOING')->where('first_name', 'Maria')->firstOrFail();
    expect($newPregnancy->id)->not->toBe($patient->id);
    expect($newPregnancy->pregnancyOutcome)->toBeNull();
    expect($newPregnancy->gravida)->toBe(3);
});

it('BA — a Confirmed Delivery row shows View Record only (no Pregnancy History button)', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    monitoringUiConfirmedDelivery($patient);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Confirmed Delivery');
    $response->assertSee('View Record');
    $response->assertSee(route('patients.show', $patient->id));
    $response->assertDontSee('Pregnancy History');
    $response->assertDontSee(route('patients.delivered.history', $patient->id));
});

it('BB — a Historical Delivered Record row keeps the Pregnancy History button', function () {
    $user = monitoringUiUser();
    $patient = monitoringUiPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subMonths(2)->toDateString()]);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));

    $response->assertSee('Historical Delivered Record');
    $response->assertSee('Pregnancy History');
    $response->assertSee(route('patients.delivered.history', $patient->id));
});