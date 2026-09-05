<?php

use App\Models\Baby;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\PregnancyOutcome;
use App\Models\User;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\PregnancyOutcomeVocabulary;

function phase17eUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase17ePatient(array $overrides = []): Patient
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

function phase17eConfirmedOutcome(Patient $patient, ?User $by = null): PregnancyOutcome
{
    return PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'confirmed_at' => now()->subDays(2),
        'confirmed_by' => $by?->id,
    ]);
}

function phase17eDeliveredPayload(): array
{
    return [
        'delivery_date' => now()->toDateString(),
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'babies' => [[
            'first_name' => 'Baby',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => now()->toDateString(),
            'time_of_birth' => '09:30',
        ]],
    ];
}

it('AC — the patient profile shows the Pregnancy Outcome card for ONGOING patients', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['edd' => now()->addDays(20)->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Pregnancy Outcome');
    $response->assertSee('Monitoring Not Yet Due');
});

it('AFE — the profile shows the CONFIRMATION_REQUIRED state for a passed-EDD patient', function () {
    $user = phase17eUser();
    $patient = phase17ePatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertSee('Outcome Confirmation Required');
});

it('AFF — an eligible staff profile shows safe modal-trigger buttons, not direct POST forms', function () {
    $user = phase17eUser();
    $patient = phase17ePatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $html = $response->getContent();

    $response->assertOk();
    $response->assertSee('data-outcome-confirm-trigger');
    $response->assertSeeHtml('data-outcome-action="' . route('pregnancy-outcomes.still-pregnant', $patient->id));
    $response->assertSeeHtml('data-outcome-action="' . route('pregnancy-outcomes.unable-to-contact', $patient->id));

    // The trigger buttons must never be wrapped in a direct POST submission form.
    expect(str_contains($html, '<form method="POST" action="' . route('pregnancy-outcomes.still-pregnant', $patient->id) . '"'))->toBeFalse();
    expect(str_contains($html, '<form method="POST" action="' . route('pregnancy-outcomes.unable-to-contact', $patient->id) . '"'))->toBeFalse();
});

it('ADF — the shared modal component is present with a CSRF-safe form', function () {
    $user = phase17eUser();
    $patient = phase17ePatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $html = $response->getContent();

    $response->assertSeeHtml('id="outcomeConfirmModal"');
    $response->assertSeeHtml('id="outcomeConfirmForm"');
    $response->assertSeeHtml('name="_token"');
    $response->assertSeeHtml('id="outcomeConfirmSubmit"');
    $response->assertSeeHtml('id="outcomeConfirmSubmitLabel"');
    expect(str_contains($html, "'Saving...'"))->toBeTrue();
    expect(str_contains($html, "event.key === 'Escape'"))->toBeTrue();
});

it('AFG — the modal targets the correct patient route for each action', function () {
    $user = phase17eUser();
    $patient = phase17ePatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $html = $response->getContent();

    expect(substr_count($html, route('pregnancy-outcomes.still-pregnant', $patient->id)))->toBeGreaterThanOrEqual(1);
    expect(substr_count($html, route('pregnancy-outcomes.unable-to-contact', $patient->id)))->toBeGreaterThanOrEqual(1);
});

it('AFH — an admin sees no mutation controls on the profile and the POST stays 403', function () {
    $admin = phase17eUser('admin');
    $patient = phase17ePatient();

    $read = $this->actingAs($admin)->get(route('patients.show', $patient->id));
    $read->assertOk();
    $read->assertDontSee('data-outcome-confirm-trigger');

    $write = $this->actingAs($admin)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $write->assertForbidden();
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AFI — future EDD profile shows no follow-up actions', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['edd' => now()->addDays(10)->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee('data-outcome-confirm-trigger');
});

it('AFJ — EDD today profile shows no follow-up actions', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['edd' => now()->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee('data-outcome-confirm-trigger');
});

it('AFK — NULL EDD profile shows no follow-up actions', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['edd' => null]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertSee('Monitoring Not Yet Due');
    $response->assertDontSee('data-outcome-confirm-trigger');
});

it('AFL — passed-EDD profile shows the follow-up actions for staff', function () {
    $user = phase17eUser();
    $patient = phase17ePatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertSee('data-outcome-confirm-trigger');
    $response->assertSee('Confirm Still Pregnant');
    $response->assertSee('Unable to Contact');
});

it('AFM — a confirmed outcome profile shows no follow-up controls', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    phase17eConfirmedOutcome($patient, $user);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Confirmed Delivery');
    $response->assertSee('This Clinic');
    $response->assertSee('Clinic Record');
    $response->assertDontSee('data-outcome-confirm-trigger');
});

it('AFN — a legacy DELIVERED profile renders without fabricated provenance', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['status' => 'DELIVERED', 'delivery_date' => now()->subMonths(2)->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Historical Delivered Record');
    $response->assertDontSee('data-outcome-confirm-trigger');
    $response->assertDontSee('This Clinic');
    $response->assertDontSee('Clinic Record');
});

it('AFO — a legacy REFERRED profile renders the read-only banner', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['status' => 'REFERRED']);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Legacy Referred Record');
});

it('AFP — the delivered history page shows only real recorded provenance', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    phase17eConfirmedOutcome($patient, $user);

    $history = $this->actingAs($user)->get(route('patients.delivered.history', $patient->id));
    $history->assertOk();
    $history->assertSee('Pregnancy History');
    $history->assertSee('Clinic Record');

    $baby = $this->actingAs($user)->get(route('patients.delivered.babies', $patient->id));
    $baby->assertOk();
    $baby->assertSee('This Clinic');
    $baby->assertSee('Clinic Record');
    $baby->assertDontSee('Normal Delivery');
    $baby->assertDontSee('Delivery Type');

    $print = $this->actingAs($user)->get(route('patients.delivered.print-babies', ['id' => $patient->id, 'all' => 1]));
    $print->assertOk();
    $print->assertDontSee('Normal Delivery');
    $print->assertDontSee('Delivery Type');
});

it('AFQ — legacy history pages stay valid with no fabricated "Normal Delivery"', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['status' => 'DELIVERED', 'delivery_date' => now()->subMonths(2)->toDateString()]);

    $history = $this->actingAs($user)->get(route('patients.delivered.history', $patient->id));
    $history->assertOk();
    $history->assertDontSee('Normal Delivery');
    $history->assertDontSee('Delivery Type');

    $baby = $this->actingAs($user)->get(route('patients.delivered.babies', $patient->id));
    $baby->assertOk();
    $baby->assertDontSee('Normal Delivery');
    $baby->assertDontSee('Delivery Type');

    $print = $this->actingAs($user)->get(route('patients.delivered.print-babies', ['id' => $patient->id, 'all' => 1]));
    $print->assertOk();
    $print->assertDontSee('Normal Delivery');
    $print->assertDontSee('Delivery Type');
});

it('AFR — the delivered patients list distinguishes Confirmed from Historical outcomes', function () {
    $user = phase17eUser();

    $modern = phase17ePatient(['first_name' => 'Ana', 'last_name' => 'Cruz', 'status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    phase17eConfirmedOutcome($modern, $user);

    phase17ePatient(['first_name' => 'Bella', 'last_name' => 'Diaz', 'status' => 'DELIVERED', 'delivery_date' => now()->subMonths(2)->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.delivered'));

    $response->assertOk();
    $response->assertSee('Confirmed');
    $response->assertSee('Historical');
    $response->assertSee('View History');
});

it('AFS — desktop and mobile monitoring views carry the same actionable information', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['first_name' => 'Carla', 'last_name' => 'Reyes']);

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index'));
    $html = $response->getContent();

    $response->assertSee('<table', false);
    $response->assertSee('Carla Reyes');
    // The desktop table and the mobile card region both exist so the same
    // patient is reachable on every breakpoint.
    expect(substr_count($html, 'Carla Reyes'))->toBeGreaterThanOrEqual(2);
});

it('AFT — start new pregnancy remains correct after Phase 17E without inheriting outcome state', function () {
    $user = phase17eUser();
    $patient = phase17ePatient(['gravida' => 2, 'para' => 1]);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), phase17eDeliveredPayload())->assertSessionHasNoErrors();

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