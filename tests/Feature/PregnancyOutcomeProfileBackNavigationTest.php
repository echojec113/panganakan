<?php

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;

function backNavUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function backNavPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Jesa',
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

function backNavConfirmedDelivery(Patient $patient): PregnancyOutcome
{
    return PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'confirmed_at' => now()->subDays(2),
    ]);
}

function backNavProfileUrl(Patient $patient, string $returnUrl): string
{
    return route('patients.show', $patient->id) . '?return=' . urlencode($returnUrl);
}

function backNavBackHref(string $html, string $expectedUrl): bool
{
    return str_contains($html, 'href="' . htmlspecialchars($expectedUrl, ENT_QUOTES, 'UTF-8') . '"');
}

// ---------------------------------------------------------------------------
// Monitoring page links carry the current context as a return URL
// ---------------------------------------------------------------------------

it('monitoring View Record / Open Profile links preserve state, search, and page as a return URL', function () {
    $user = backNavUser();
    for ($i = 1; $i <= 20; $i++) {
        $p = backNavPatient(['first_name' => 'Jesa', 'last_name' => 'Reyes' . $i, 'status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
        backNavConfirmedDelivery($p);
    }

    $response = $this->actingAs($user)->get(route('pregnancy-outcomes.index', ['state' => 'resolved', 'search' => 'Jesa', 'page' => 2]));

    $response->assertOk();
    $response->assertSee('Jesa Reyes16');
    $html = $response->getContent();

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'resolved', 'search' => 'Jesa', 'page' => 2]);
    expect($html)->toContain('return=' . urlencode($monitoringUrl));
    expect($html)->toContain('/patients/');
    expect($html)->toContain('?return=');
});

// ---------------------------------------------------------------------------
// Back restores the previous monitoring view for each state
// ---------------------------------------------------------------------------

it('Back returns to the Confirmed Delivery monitoring view', function () {
    $user = backNavUser();
    $patient = backNavPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    backNavConfirmedDelivery($patient);

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'resolved']);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $monitoringUrl));

    $response->assertOk();
    $response->assertSee('Back');
    expect(backNavBackHref($response->getContent(), $monitoringUrl))->toBeTrue();
});

it('Back returns to the Outcome Confirmation Required monitoring view', function () {
    $user = backNavUser();
    $patient = backNavPatient();

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'confirmation-required']);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $monitoringUrl));

    $response->assertOk();
    $response->assertSee('Outcome Confirmation Required');
    expect(backNavBackHref($response->getContent(), $monitoringUrl))->toBeTrue();
});

it('Back returns to the Still Pregnant — Confirmed monitoring view', function () {
    $user = backNavUser();
    $patient = backNavPatient();
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => now()->subDays(2),
        'follow_up_recorded_by' => $user->id,
    ]);

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'still-pregnant']);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $monitoringUrl));

    $response->assertOk();
    $response->assertSee('Still Pregnant');
    expect(backNavBackHref($response->getContent(), $monitoringUrl))->toBeTrue();
});

it('Back returns to the Unable to Contact monitoring view', function () {
    $user = backNavUser();
    $patient = backNavPatient();
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
        'follow_up_recorded_at' => now()->subDays(1),
        'follow_up_recorded_by' => $user->id,
    ]);

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'unable-to-contact']);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $monitoringUrl));

    $response->assertOk();
    $response->assertSee('Unable to Contact');
    expect(backNavBackHref($response->getContent(), $monitoringUrl))->toBeTrue();
});

it('Back preserves a monitoring search query and pagination page', function () {
    $user = backNavUser();
    $patient = backNavPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    backNavConfirmedDelivery($patient);

    $monitoringUrl = route('pregnancy-outcomes.index', ['state' => 'resolved', 'search' => 'Jesa', 'page' => 2]);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $monitoringUrl));

    expect(backNavBackHref($response->getContent(), $monitoringUrl))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Safe fallback when opened directly
// ---------------------------------------------------------------------------

it('uses the plain monitoring page as the safe fallback when opened directly', function () {
    $user = backNavUser();
    $patient = backNavPatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Back');
    expect(backNavBackHref($response->getContent(), route('pregnancy-outcomes.index')))->toBeTrue();
});

// ---------------------------------------------------------------------------
// External / malicious return URLs are rejected
// ---------------------------------------------------------------------------

it('rejects an external return URL and uses the safe fallback', function () {
    $user = backNavUser();
    $patient = backNavPatient();

    $badUrl = 'https://evil.example.com/phish';
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $badUrl));

    $response->assertOk();
    expect(backNavBackHref($response->getContent(), route('pregnancy-outcomes.index')))->toBeTrue();
    expect(backNavBackHref($response->getContent(), $badUrl))->toBeFalse();
});

it('rejects a javascript-scheme return URL and uses the safe fallback', function () {
    $user = backNavUser();
    $patient = backNavPatient();

    $badUrl = 'javascript:alert(1)';
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $badUrl));

    $response->assertOk();
    expect(backNavBackHref($response->getContent(), route('pregnancy-outcomes.index')))->toBeTrue();
    expect(backNavBackHref($response->getContent(), $badUrl))->toBeFalse();
});

it('rejects an internal-but-wrong-path return URL and uses the safe fallback', function () {
    $user = backNavUser();
    $patient = backNavPatient();

    $badUrl = route('patients.show', 999999);
    $response = $this->actingAs($user)->get(backNavProfileUrl($patient, $badUrl));

    $response->assertOk();
    expect(backNavBackHref($response->getContent(), route('pregnancy-outcomes.index')))->toBeTrue();
    expect(backNavBackHref($response->getContent(), $badUrl))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Pregnancy History button removal inside the Pregnancy Outcome card
// ---------------------------------------------------------------------------

it('removes the Pregnancy History button from the Pregnancy Outcome card but keeps the header action', function () {
    $user = backNavUser();
    $patient = backNavPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    backNavConfirmedDelivery($patient);

    $html = $this->actingAs($user)->get(route('patients.show', $patient->id))->getContent();

    $historyLink = route('patients.delivered.history', $patient->id);
    expect(substr_count($html, $historyLink))->toBe(1);
    expect($html)->toContain('View Pregnancy History');
});

it('keeps the Pregnancy History feature reachable from its normal location', function () {
    $user = backNavUser();
    $patient = backNavPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(2)->toDateString()]);
    backNavConfirmedDelivery($patient);

    $history = $this->actingAs($user)->get(route('patients.delivered.history', $patient->id));

    $history->assertOk();
    $history->assertSeeText('Pregnancy History');
});