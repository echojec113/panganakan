<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function phpStaffUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function phpPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'History',
        'last_name' => 'Print',
        'age' => 29,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phpVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 24,
        'risk_level' => 'LOW',
        'assessment' => 'Default assessment text',
        'recommendation' => 'Default recommendation text',
    ], $overrides));
}

function phpStorePayload(array $overrides = []): array
{
    return array_merge([
        'patient_id' => null,
        'visit_date' => now()->toDateString(),
        'bp_sys' => '120',
        'bp_dia' => '80',
        'weight' => '60',
        'gestational_age' => '20',
        'hypertension' => '0',
        'diabetes' => '0',
        'anemia' => '0',
    ], $overrides);
}

// ---------------------------------------------------------------------
// A. Every prenatal visit must remain separately saved
// ---------------------------------------------------------------------

it('creates a new database row for every prenatal visit stored', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), phpStorePayload(['patient_id' => $patient->id, 'visit_date' => now()->subDays(2)->toDateString()]));
    $this->actingAs($user)->post(route('prenatal-visits.store'), phpStorePayload(['patient_id' => $patient->id, 'visit_date' => now()->toDateString()]));

    $this->assertDatabaseCount('prenatal_visits', 2);
    expect(PrenatalVisit::where('patient_id', $patient->id)->count())->toBe(2);
});

it('does not increase the row count when updating an existing prenatal visit', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), phpStorePayload(['patient_id' => $patient->id]));
    $visit = PrenatalVisit::where('patient_id', $patient->id)->firstOrFail();

    $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), phpStorePayload([
        'patient_id' => $patient->id,
        'weight' => '65',
        'notes' => 'Updated during edit',
    ]));

    $this->assertDatabaseCount('prenatal_visits', 1);
    $visit->refresh();
    expect($visit->weight)->toBe(65);
    expect($visit->notes)->toBe('Updated during edit');
});

it('selects the visit with the latest visit_date as the current assessment', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $older = phpVisit($patient->id, [
        'visit_date' => now()->subDays(10)->toDateString(),
        'risk_level' => 'LOW',
        'assessment' => 'Older LOW assessment text',
    ]);
    $newer = phpVisit($patient->id, [
        'visit_date' => now()->subDays(1)->toDateString(),
        'risk_level' => 'HIGH',
        'assessment' => 'Newer HIGH assessment text',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    // The current-assessment panel headline reflects the visit with the
    // latest visit_date (HIGH), not the older LOW visit.
    $response->assertSeeText('HIGH RISK');
    $response->assertDontSeeText('LOW RISK');
});

it('uses the highest id as a tie-breaker when visit_date is the same', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $sameDate = now()->toDateString();

    $first = phpVisit($patient->id, [
        'visit_date' => $sameDate,
        'risk_level' => 'LOW',
        'assessment' => 'First same-date assessment text',
    ]);
    $second = phpVisit($patient->id, [
        'visit_date' => $sameDate,
        'risk_level' => 'HIGH',
        'assessment' => 'Second same-date assessment text',
    ]);

    expect($second->id)->toBeGreaterThan($first->id);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    // The current-assessment panel headline reflects the highest id when
    // visit_date is tied (HIGH), not the earlier same-date LOW visit.
    $response->assertSeeText('HIGH RISK');
    $response->assertDontSeeText('LOW RISK');
});

it('ignores soft-deleted visits when selecting the current assessment and history', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $kept = phpVisit($patient->id, [
        'visit_date' => now()->subDays(5)->toDateString(),
        'risk_level' => 'LOW',
        'assessment' => 'Kept visible assessment text',
    ]);
    $deleted = phpVisit($patient->id, [
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'assessment' => 'Soft deleted assessment text',
    ]);
    $deleted->delete();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Kept visible assessment text');
    $response->assertDontSeeText('Soft deleted assessment text');
});

// ---------------------------------------------------------------------
// B. Assessment History in Patient Profile
// ---------------------------------------------------------------------

it('shows every non-deleted prenatal visit in the Patient Profile history table', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    phpVisit($patient->id, ['visit_date' => now()->subDays(3)->toDateString(), 'assessment' => 'Visit one assessment text']);
    phpVisit($patient->id, ['visit_date' => now()->subDays(2)->toDateString(), 'assessment' => 'Visit two assessment text']);
    phpVisit($patient->id, ['visit_date' => now()->subDays(1)->toDateString(), 'assessment' => 'Visit three assessment text']);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Visit one assessment text');
    $response->assertSeeText('Visit two assessment text');
    $response->assertSeeText('Visit three assessment text');
});

it('orders the Patient Profile visit history by visit_date desc then id desc', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $sameDate = now()->subDays(1)->toDateString();

    $oldest = phpVisit($patient->id, ['visit_date' => now()->subDays(5)->toDateString()]);
    $tieA = phpVisit($patient->id, ['visit_date' => $sameDate]);
    $tieB = phpVisit($patient->id, ['visit_date' => $sameDate]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $response->assertOk();

    $content = $response->getContent();
    $posTieB = strpos($content, 'visit-details-' . $tieB->id);
    $posTieA = strpos($content, 'visit-details-' . $tieA->id);
    $posOldest = strpos($content, 'visit-details-' . $oldest->id);

    expect($posTieB)->not->toBeFalse();
    expect($posTieA)->not->toBeFalse();
    expect($posOldest)->not->toBeFalse();

    // Newest visit_date first; when tied, highest id first.
    expect($posTieB)->toBeLessThan($posTieA);
    expect($posTieA)->toBeLessThan($posOldest);
});

// ---------------------------------------------------------------------
// C/D/E/F. Per-visit Print action, route, controller, and print view data
// ---------------------------------------------------------------------

it('shows a Print action inside the Action column for every prenatal visit row', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $visitOne = phpVisit($patient->id, ['visit_date' => now()->subDays(1)->toDateString()]);
    $visitTwo = phpVisit($patient->id, ['visit_date' => now()->toDateString()]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('>Edit<', false);
    $response->assertSee('>Delete<', false);
    $response->assertSee(route('prenatal-visits.print', $visitOne->id));
    $response->assertSee(route('prenatal-visits.print', $visitTwo->id));
});

it('points each Print action to that specific prenatal visit id', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $visit = phpVisit($patient->id);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('href="' . route('prenatal-visits.print', $visit->id) . '"', false);
});

it('renders the selected visit own persisted assessment data on the print route', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $visit = phpVisit($patient->id, [
        'risk_level' => 'HIGH',
        'assessment' => 'Printable HIGH assessment text',
        'recommendation' => 'Printable recommendation text',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
    ]);

    $response = $this->actingAs($user)->get(route('prenatal-visits.print', $visit->id));

    $response->assertOk();
    $response->assertSeeText('Printable HIGH assessment text');
    $response->assertSeeText('Printable recommendation text');
    $response->assertSeeText('Hypertension');
    $response->assertSee('window.print()', false);
});

it('does not render another visit assessment when printing a specific visit', function () {
    $user = phpStaffUser();
    $patient = phpPatient();

    $visitA = phpVisit($patient->id, [
        'visit_date' => now()->subDays(5)->toDateString(),
        'assessment' => 'Visit A only assessment text',
    ]);
    $visitB = phpVisit($patient->id, [
        'visit_date' => now()->toDateString(),
        'assessment' => 'Visit B only assessment text',
    ]);

    $responseA = $this->actingAs($user)->get(route('prenatal-visits.print', $visitA->id));
    $responseA->assertOk();
    $responseA->assertSeeText('Visit A only assessment text');
    $responseA->assertDontSeeText('Visit B only assessment text');

    $responseB = $this->actingAs($user)->get(route('prenatal-visits.print', $visitB->id));
    $responseB->assertOk();
    $responseB->assertSeeText('Visit B only assessment text');
    $responseB->assertDontSeeText('Visit A only assessment text');
});

it('never leaks another patient identity/assessment when printing across two separate patients', function () {
    // This mirrors the project's existing authorization model: the same
    // authenticated staff/admin user is allowed to view any patient's
    // profile (patients.show has no per-record ownership check), and the
    // print route intentionally sits in that same auth-only tier. The
    // security property being verified here is that changing {visit} can
    // never make Patient A's context "leak" into Patient B's assessment,
    // or vice versa — the visit ID always resolves to its own true patient.
    $user = phpStaffUser();

    $patientA = phpPatient(['first_name' => 'Alpha', 'last_name' => 'Isolated']);
    $visitA = phpVisit($patientA->id, [
        'assessment' => 'Alpha exclusive assessment text',
        'risk_level' => 'LOW',
    ]);

    $patientB = phpPatient(['first_name' => 'Bravo', 'last_name' => 'Isolated']);
    $visitB = phpVisit($patientB->id, [
        'assessment' => 'Bravo exclusive assessment text',
        'risk_level' => 'HIGH',
    ]);

    // Establish "Patient A's context" by visiting Patient A's profile first.
    $this->actingAs($user)->get(route('patients.show', $patientA->id))->assertOk();

    // Requesting visit B's print, while authenticated as the same staff
    // user who was just viewing Patient A, must render Bravo's own data —
    // never Alpha's identity or assessment.
    $responseB = $this->actingAs($user)->get(route('prenatal-visits.print', $visitB->id));
    $responseB->assertOk();
    $responseB->assertSeeText('Bravo');
    $responseB->assertSeeText('Bravo exclusive assessment text');
    $responseB->assertDontSeeText('Alpha');
    $responseB->assertDontSeeText('Alpha exclusive assessment text');

    // Symmetric check: visit A's print must never render Bravo's data.
    $responseA = $this->actingAs($user)->get(route('prenatal-visits.print', $visitA->id));
    $responseA->assertOk();
    $responseA->assertSeeText('Alpha');
    $responseA->assertSeeText('Alpha exclusive assessment text');
    $responseA->assertDontSeeText('Bravo');
    $responseA->assertDontSeeText('Bravo exclusive assessment text');
});

it('rejects an unauthenticated request to the prenatal visit print route', function () {
    $patient = phpPatient();
    $visit = phpVisit($patient->id);

    $response = $this->get(route('prenatal-visits.print', $visit->id));

    $response->assertRedirect(route('login'));
});

it('allows an admin to print a prenatal visit, matching the existing auth-only Patient Profile view tier', function () {
    // patients.show (Patient Profile) and prenatal-visits.print both sit
    // outside the staff-only write-route group, so both roles that can
    // view a Patient Profile (staff and admin) can also view its print.
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = phpPatient();
    $visit = phpVisit($patient->id, ['assessment' => 'Admin-visible assessment text']);

    $profileResponse = $this->actingAs($admin)->get(route('patients.show', $patient->id));
    $profileResponse->assertOk();

    $printResponse = $this->actingAs($admin)->get(route('prenatal-visits.print', $visit->id));
    $printResponse->assertOk();
    $printResponse->assertSeeText('Admin-visible assessment text');
});

it('returns 404 for a print request against a non-existent visit id', function () {
    $user = phpStaffUser();

    $response = $this->actingAs($user)->get(route('prenatal-visits.print', 999999));

    $response->assertNotFound();
});

// ---------------------------------------------------------------------
// Existing behavior must remain intact
// ---------------------------------------------------------------------

it('still allows editing an existing prenatal visit through the existing Edit action', function () {
    $user = phpStaffUser();
    $patient = phpPatient();
    $visit = phpVisit($patient->id);

    $response = $this->actingAs($user)->get(route('prenatal-visits.edit', $visit->id));

    $response->assertOk();
});

it('still allows deleting an existing prenatal visit through the existing Delete action', function () {
    $user = phpStaffUser();
    $patient = phpPatient();
    $visit = phpVisit($patient->id);

    $this->actingAs($user)->delete(route('prenatal-visits.destroy', $visit->id));

    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);
});

it('still shows the existing risk panel behavior on the Patient Profile', function () {
    $user = phpStaffUser();
    $patient = phpPatient();
    phpVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'assessment' => 'High risk assessment',
        'recommendation' => 'Follow-up required.',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('HIGH RISK');
});
