<?php

use App\Models\Baby;
use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * DEPLA Phase 3H.4 / 3H.4A — Patient Portal Pregnancy History (read-only,
 * DELIVERED episodes only). SQLite :memory: only (phpunit.xml forces).
 * Covers the strict verified-link ownership contract for historical
 * episodes, the lifecycle-automatic delivered-only listing (ONGOING
 * pointer excluded; DELIVERED pointer included; no Current card), the
 * seven-section historical records view (reusing the Phase 3E.2/3E.3
 * allow-lists), the authorization matrix, the absence of any
 * write/switch/link endpoint, the pointer-without-link integrity anomaly,
 * the no-GET-writes guarantee, the exact empty-state wording, and
 * regression probes for the unchanged current Medical Records page.
 */

beforeEach(function () {
    $this->withoutVite();
});

function phase3h4User(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase3h4Patient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 28,
        'birthdate' => '1998-04-12',
        'email' => strtolower(uniqid('h4.', true)) . '@phase3h4.test',
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'lmp' => '2026-01-15',
        'edd' => '2026-10-22',
    ], $overrides));
}

function phase3h4LinkRow(User $user, Patient $patient): UserPregnancyLink
{
    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_by = null;
    $link->verified_at = now();
    $link->save();

    return $link;
}

/** Fully-verified CURRENT episode: pointer + membership row (3H.2B/3H.3 state). */
function phase3h4VerifiedCurrent(User $user, array $overrides = []): Patient
{
    $patient = phase3h4Patient($overrides);
    $patient->user_id = $user->id;
    $patient->save();
    phase3h4LinkRow($user, $patient);

    return $patient;
}

/** Verified PREVIOUS episode: membership row only, never the pointer. Defaults to DELIVERED. */
function phase3h4VerifiedPrevious(User $user, array $overrides = []): Patient
{
    $patient = phase3h4Patient(array_merge([
        'status' => 'DELIVERED',
        'lmp' => '2024-06-01',
        'edd' => '2025-03-08',
        'delivery_date' => '2025-03-05',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));

    phase3h4LinkRow($user, $patient);

    return $patient;
}

function phase3h4Visit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => '2025-01-10',
        'gestational_age' => 31.0,
        'bp_sys' => 118,
        'bp_dia' => 76,
        'weight' => 62.5,
        'temperature' => 36.6,
        'fundic_height' => '31 cm',
        'fetal_heart_tone' => 'Regular 140',
        'fetal_movement' => 'Active',
        'next_visit_date' => '2025-01-17',
    ], $overrides));
}

function phase3h4History(Patient $patient, array $overrides = []): MedicalHistory
{
    return MedicalHistory::create(array_merge([
        'patient_id' => $patient->id,
        'epilepsy' => true,
        'diabetes' => true,
        'smoking' => false,
    ], $overrides));
}

function phase3h4Scan(Patient $patient, array $overrides = []): Ultrasound
{
    return Ultrasound::create(array_merge([
        'patient_id' => $patient->id,
        'scan_date' => '2025-01-05',
        'fetal_heartbeat' => 'Normal',
        'fetal_movement' => 'Normal',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'gestational_age_scan' => 30,
        'estimated_fetal_weight' => 1500.0,
    ], $overrides));
}

function phase3h4Plan(Patient $patient, array $overrides = []): BirthPlan
{
    return BirthPlan::create(array_merge([
        'patient_id' => $patient->id,
        'planned_visits' => 8,
        'deliver_in_clinic' => 1,
        'delivery_location' => 'Lying-in Clinic A',
        'transportation' => 'Family car',
        'transport_cost' => 0,
        'payment_method' => 'Cash',
        'saving_started' => 1,
        'birth_companion' => 'Husband',
        'caregiver_home' => 'Mother',
        'plan_more_children' => 1,
        'number_more_children' => 2,
        'knows_fp_method' => 1,
        'used_fp_before' => 0,
        'family_planning_method' => 'Pills',
        'fp_source' => 'Health center',
    ], $overrides));
}

function phase3h4Outcome(Patient $patient, array $overrides = []): PregnancyOutcome
{
    return PregnancyOutcome::create(array_merge([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
    ], $overrides));
}

function phase3h4Baby(Patient $patient, array $overrides = []): Baby
{
    return Baby::create(array_merge([
        'patient_id' => $patient->id,
        'first_name' => 'Miguel',
        'middle_name' => 'Santos',
        'last_name' => 'Santos',
        'sex' => 'Male',
        'date_of_birth' => '2025-03-05',
        'time_of_birth' => '08:30',
        'birth_weight' => 3.25,
        'birth_length' => 50.0,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| 1–2. Listing: verified delivered previous episodes
|--------------------------------------------------------------------------
*/

it('shows a verified previous delivered pregnancy in the listing', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('Pregnancy History')
        ->assertSee('View your previous delivered pregnancies and their medical records.')
        ->assertSee('Previous Delivered Pregnancies')
        // Cards carry the Delivered badge + dates (no raw status string).
        ->assertSee('Delivered Pregnancy')
        ->assertSee('Mar 05, 2025')
        ->assertSee('View Medical Records')
        ->assertSee(route('patient.pregnancies.show', ['episodeId' => $previous->id]), false);
});

it('orders previous delivered pregnancies by most recent clinical date first', function () {
    $user = phase3h4User();
    $older = phase3h4VerifiedPrevious($user, ['delivery_date' => '2023-01-10', 'edd' => '2023-01-15', 'lmp' => '2022-04-01']);
    $newer = phase3h4VerifiedPrevious($user, ['delivery_date' => '2025-06-15', 'edd' => '2025-06-20', 'lmp' => '2024-09-01']);
    phase3h4VerifiedCurrent($user);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    $newerPos = strpos($html, 'Jun 15, 2025');
    $olderPos = strpos($html, 'Jan 10, 2023');

    expect($newerPos)->not->toBeFalse();
    expect($olderPos)->not->toBeFalse();
    expect($newerPos)->toBeLessThan($olderPos);
});

/*
|--------------------------------------------------------------------------
| 3–4. Current pregnancy visibility: ONGOING excluded, DELIVERED included
|--------------------------------------------------------------------------
*/

it('excludes the current ONGOING pregnancy from the listing', function () {
    $user = phase3h4User();
    $current = phase3h4VerifiedCurrent($user, ['status' => 'ONGOING']);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertDontSee('Current Pregnancy')
        ->assertDontSee('Current</span>')
        ->getContent();

    // The ONGOING current dates never appear; the shared nav's legitimate
    // "My Medical Records" link (present on every portal page) is not a
    // current-pregnancy card, so no route assertion is made here.
    expect($html)->not->toContain('Oct 22, 2026');
});

it('includes a current DELIVERED pointer episode in the listing and via show', function () {
    $user = phase3h4User();

    // Lifecycle rule: once the pointer episode's status becomes DELIVERED
    // (e.g. delivery recorded but Start New Pregnancy not yet used), it
    // appears in Pregnancy History automatically - no continuation step,
    // no linking action. The membership row from provisioning already
    // establishes ownership.
    $currentDelivered = phase3h4VerifiedCurrent($user, [
        'status' => 'DELIVERED',
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
        'delivery_date' => '2025-10-05',
    ]);
    $previous = phase3h4VerifiedPrevious($user, ['delivery_date' => '2023-06-01']);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    // Both the genuine previous episode and the current DELIVERED pointer are listed.
    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $previous->id]));
    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $currentDelivered->id]));

    // The pointer episode's delivery date is rendered on its history card.
    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('Oct 05, 2025');

    // Direct URL to the current DELIVERED pointer resolves read-only.
    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $currentDelivered->id]))
        ->assertOk()
        ->assertSee('Back to Pregnancy History');
});

/*
|--------------------------------------------------------------------------
| 5–8. Listing exclusions: unverified, foreign, archived, ONGOING historical
|--------------------------------------------------------------------------
*/

it('excludes an unverified delivered pregnancy from the listing', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user);

    // Same woman, earlier delivered episode, NO membership row: never listed.
    $unverified = phase3h4Patient([
        'status' => 'DELIVERED',
        'delivery_date' => '2023-05-01',
        'lmp' => '2022-06-01',
        'edd' => '2023-03-08',
    ]);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('You have no previous delivered pregnancy records available yet.')
        ->assertDontSee(route('patient.pregnancies.show', ['episodeId' => $unverified->id]), false);
});

it('does not automatically link same-name same-birthdate records', function () {
    $user = phase3h4User();
    $current = phase3h4VerifiedCurrent($user);

    // Demographic twin of the CURRENT episode: identical name and birthdate,
    // no explicit membership row. It must never appear in the listing and
    // must never be reachable through the show route.
    $twin = phase3h4Patient([
        'first_name' => $current->first_name,
        'last_name' => $current->last_name,
        'birthdate' => $current->birthdate,
        'status' => 'DELIVERED',
        'delivery_date' => '2022-02-02',
    ]);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertDontSee(route('patient.pregnancies.show', ['episodeId' => $twin->id]), false);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $twin->id]))
        ->assertNotFound();

    expect(UserPregnancyLink::where('patient_id', $twin->id)->exists())->toBeFalse();
});

it('excludes another account delivered pregnancy from the listing', function () {
    $userA = phase3h4User();
    $userB = phase3h4User();

    phase3h4VerifiedCurrent($userA);
    phase3h4VerifiedCurrent($userB);
    $previousB = phase3h4VerifiedPrevious($userB);

    $html = $this->actingAs($userA)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(
        route('patient.pregnancies.show', ['episodeId' => $previousB->id])
    );
});

it('excludes an archived delivered pregnancy from the listing', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    $previous->delete();

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('You have no previous delivered pregnancy records available yet.')
        ->assertDontSee(route('patient.pregnancies.show', ['episodeId' => $previous->id]), false);
});

it('excludes a verified ONGOING historical episode from the listing and from show', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user);

    // Membership row exists but the episode is ONGOING (e.g. data anomaly):
    // Pregnancy History is delivered-only, so it never appears.
    $ongoingHistorical = phase3h4VerifiedPrevious($user, [
        'status' => 'ONGOING',
        'delivery_date' => null,
        'lmp' => '2025-08-01',
        'edd' => '2026-05-08',
    ]);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertDontSee(route('patient.pregnancies.show', ['episodeId' => $ongoingHistorical->id]), false);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $ongoingHistorical->id]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 9–13. Direct URL authorization matrix
|--------------------------------------------------------------------------
*/

it('returns 404 for a direct foreign delivered pregnancy URL', function () {
    $userA = phase3h4User();
    $userB = phase3h4User();

    phase3h4VerifiedCurrent($userA);
    $foreign = phase3h4VerifiedPrevious($userB);

    $this->actingAs($userA)
        ->get(route('patient.pregnancies.show', ['episodeId' => $foreign->id]))
        ->assertNotFound();
});

it('returns 404 for an unlinked delivered pregnancy URL', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user);

    $unlinked = phase3h4Patient(['status' => 'DELIVERED']);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $unlinked->id]))
        ->assertNotFound();
});

it('returns 404 for an archived delivered pregnancy URL', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    // The membership row survives the archive (RESTRICT + SoftDeletes) but
    // the episode itself is hidden, so the two-step lookup must 404.
    $previous->delete();

    expect(UserPregnancyLink::where('patient_id', $previous->id)->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $previous->id]))
        ->assertNotFound();
});

it('returns 403 when the current pregnancy pointer is missing', function () {
    $user = phase3h4User();

    $this->actingAs($user)->get(route('patient.pregnancies.index'))->assertForbidden();
    $this->actingAs($user)->get(route('patient.pregnancies.show', ['episodeId' => 1]))->assertForbidden();
});

it('returns 403 when the current pointer is archived', function () {
    $user = phase3h4User();
    $current = phase3h4VerifiedCurrent($user);

    $current->delete();

    $this->actingAs($user)->get(route('patient.pregnancies.index'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 15–17. Authorization matrix (role, guest, password change)
|--------------------------------------------------------------------------
*/

it('requires the patient role', function () {
    $staff = phase3h4User('staff');
    $admin = phase3h4User('admin');

    $this->actingAs($staff)->get(route('patient.pregnancies.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('patient.pregnancies.show', ['episodeId' => 1]))->assertForbidden();
    $this->actingAs($admin)->get(route('patient.pregnancies.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('patient.pregnancies.show', ['episodeId' => 1]))->assertForbidden();
});

it('redirects a guest to login', function () {
    $this->get(route('patient.pregnancies.index'))->assertRedirect(route('login'));
    $this->get(route('patient.pregnancies.show', ['episodeId' => 1]))->assertRedirect(route('login'));
});

it('still forces the password change before history access', function () {
    $user = phase3h4User('patient', flagged: true);
    phase3h4VerifiedCurrent($user);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => 1]))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

/*
|--------------------------------------------------------------------------
| 14–15. Seven-section historical records view + privacy
|--------------------------------------------------------------------------
*/

it('renders all seven sections with safe data for a verified historical delivered episode', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    phase3h4Visit($previous);
    phase3h4History($previous);
    phase3h4Scan($previous);
    phase3h4Plan($previous);
    phase3h4Outcome($previous);
    phase3h4Baby($previous);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $previous->id]))
        ->assertOk()
        ->assertSee('Back to Pregnancy History')
        ->assertSee('You are viewing a previous pregnancy')
        // The seven existing tabs, in order.
        ->assertSee('Pregnancy Overview')
        ->assertSee('Prenatal Visit History')
        ->assertSee('Medical History')
        ->assertSee('Ultrasound Records')
        ->assertSee('Birth Plan')
        ->assertSee('Delivery Records')
        ->assertSee('Baby Information')
        // Safe data from each section.
        ->assertSee('DELIVERED')
        ->assertSee('Mar 05, 2025')
        ->assertSee('Jan 10, 2025')
        ->assertSee('1500')
        ->assertSee('Miguel Santos');
});

it('never renders staff-only notes, risk, ML or confirmation provenance on the historical page', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    $staff = phase3h4User('staff');

    phase3h4Visit($previous, [
        'risk_level' => 'HIGH',
        'risk_reasons' => ['MARKER-RISK-REASON'],
        'ml_prediction' => 'MARKER-ML-PREDICTION',
        'assessment' => 'MARKER-INTERNAL-ASSESSMENT',
        'notes' => 'MARKER-VISIT-NOTES',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'factor_evidence' => ['MARKER-FACTOR-EVIDENCE'],
    ]);
    phase3h4Scan($previous, [
        'remarks' => 'MARKER-US-REMARKS',
        'report_file' => 'reports/MARKER-REPORT-FILE.pdf',
        'report_image' => 'reports/MARKER-REPORT-IMAGE.jpg',
    ]);
    phase3h4Plan($previous, ['notes' => 'MARKER-BIRTH-PLAN-NOTES']);
    phase3h4Outcome($previous, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'confirmed_by' => $staff->id,
        'notes' => 'MARKER-OUTCOME-NOTES',
    ]);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $previous->id]))
        ->assertOk()
        ->getContent();

    foreach ([
        'MARKER-RISK-REASON',
        'MARKER-ML-PREDICTION',
        'MARKER-INTERNAL-ASSESSMENT',
        'MARKER-VISIT-NOTES',
        'URGENT_CLINICAL_REVIEW',
        'MARKER-FACTOR-EVIDENCE',
        'MARKER-US-REMARKS',
        'MARKER-REPORT-FILE',
        'MARKER-REPORT-IMAGE',
        'MARKER-BIRTH-PLAN-NOTES',
        'MARKER-OUTCOME-NOTES',
        'STILL_PREGNANT_CONFIRMED',
        'CLINIC_RECORD',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered forbidden value [$forbidden].");
    }
});

/*
|--------------------------------------------------------------------------
| 16. Empty state
|--------------------------------------------------------------------------
*/

it('shows the delivered-only empty state with no current card fallback', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user); // pointer only - no delivered history

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('You have no previous delivered pregnancy records available yet.')
        ->assertDontSee('Current Pregnancy')
        ->assertDontSee('Link Previous Pregnancy');
});

/*
|--------------------------------------------------------------------------
| 17–18. No write, switch or link controls; no GET writes
|--------------------------------------------------------------------------
*/

it('introduces no write, switching or linking pregnancy-history routes or controls', function () {
    $historyRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'patient/pregnancies'));

    expect($historyRoutes->count())->toBe(2);

    foreach ($historyRoutes as $route) {
        $methods = array_diff($route->methods(), ['GET', 'HEAD']);

        expect($methods)->toBeEmpty(
            "Route [{$route->uri()}] exposes non-GET methods: " . implode(',', $methods)
        );
    }

    // No switching / editing / linking vocabulary anywhere on the history pages.
    $user = phase3h4User();
    phase3h4VerifiedPrevious($user);
    phase3h4VerifiedCurrent($user);

    $html = $this->actingAs($user)->get(route('patient.pregnancies.index'))->getContent();

    foreach ([
        'Make Current',
        'Switch Pregnancy',
        'Link Pregnancy',
        'Link Previous Pregnancy',
        'Unlink Pregnancy',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden);
    }

    // POST is exempt: the shared layout's logout form legitimately uses it.
    foreach (['method="PUT"', 'method="PATCH"', 'method="DELETE"'] as $forbidden) {
        expect($html)->not->toContain($forbidden);
    }
});

/*
|--------------------------------------------------------------------------
| 19–20. Current page unchanged, pointer anomaly, no GET writes
|--------------------------------------------------------------------------
*/

it('keeps the current medical-records page unchanged', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user);

    // The current page keeps its original wording and back-link. (The
    // shared layout now carries the Pregnancy History NAV item on every
    // portal page by design, so no nav assertion is made here - only the
    // page CONTENT must stay current-pregnancy scoped.)
    $this->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('My Medical Records')
        ->assertSee('Records for your currently linked pregnancy.')
        ->assertSee('Back to Dashboard')
        ->assertDontSee('You are viewing a previous pregnancy');
});

it('does not grant historical authorization through a pointer-only fallback', function () {
    $user = phase3h4User();

    // INTEGRITY ANOMALY: a valid pointer WITHOUT its expected membership
    // row (e.g. a pre-3H.2 account whose backfill never ran). The listing
    // shows only the delivered-only empty state (no Current card, no
    // fallback), and the historical show route refuses the episode: strict
    // membership only, and the GET must never invent the missing link row.
    $pointerOnly = phase3h4Patient();
    $pointerOnly->user_id = $user->id;
    $pointerOnly->save();

    expect(UserPregnancyLink::count())->toBe(0);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('You have no previous delivered pregnancy records available yet.')
        ->assertDontSee('Current Pregnancy');

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $pointerOnly->id]))
        ->assertNotFound();

    // No link was invented by the GET requests.
    expect(UserPregnancyLink::count())->toBe(0);
});

it('performs no writes to user_pregnancy_links or pointers during history GET requests', function () {
    $user = phase3h4User();
    $previous = phase3h4VerifiedPrevious($user);
    $current = phase3h4VerifiedCurrent($user);

    $linksBefore = UserPregnancyLink::count();
    $pointersBefore = DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all();

    $this->actingAs($user)->get(route('patient.pregnancies.index'))->assertOk();
    $this->actingAs($user)->get(route('patient.pregnancies.show', ['episodeId' => $previous->id]))->assertOk();
    // The current pointer episode while ONGOING is out of scope: 404, not 200.
    $this->actingAs($user)->get(route('patient.pregnancies.show', ['episodeId' => $current->id]))->assertNotFound();

    expect(UserPregnancyLink::count())->toBe($linksBefore);
    expect(DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all())->toBe($pointersBefore);
});

/*
|--------------------------------------------------------------------------
| Navigation
|--------------------------------------------------------------------------
*/

it('exposes Pregnancy History in the portal navigation on dashboard and history pages', function () {
    $user = phase3h4User();
    phase3h4VerifiedCurrent($user);

    // Dashboard and the history page both carry the shared layout nav item
    // (the same markup serves the desktop nav and the mobile menu toggle).
    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Pregnancy History')
        ->assertSee(route('patient.pregnancies.index'), false)
        ->assertSee('portal-menu-button');

    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('Pregnancy History')
        ->assertSee('My Medical Records')
        ->assertSee(route('patient.medical-records.index'), false);
});
