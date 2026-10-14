<?php

use App\Models\Baby;
use App\Models\BirthPlan;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Phase 3E.3 — Patient Portal "My Medical Records" expansion
|--------------------------------------------------------------------------
| Ultrasound Records, Birth Plan, Delivery Records and Baby Information on
| the existing Phase 3E.2 page. SQLite :memory: only (phpunit.xml forces).
| Covers the four new sections, their allow-lists and formatting, the
| ownership boundary (another patient AND a name-matched previous pregnancy
| stay invisible), the staff-only / file-path exclusion list, and regression
| probes proving the Phase 3E.2 sections and the authorization matrix are
| unchanged. No route was added: the page is still ONE GET endpoint.
*/

function phase3e3User(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase3e3Patient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'middle_name' => 'Dela Cruz',
        'age' => 27,
        'birthdate' => '1999-02-08',
        'email' => 'ana.expansion@phase3e3.test',
        'contact_number' => '09179876543',
        'address_line' => '45 Bonifacio Street',
        'barangay' => 'Barangay 88',
        'city_municipality' => 'Quezon City',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'lmp' => '2026-02-01',
        'edd' => '2026-11-08',
        'previous_cs' => 1,
        'miscarriage' => 1,
    ], $overrides));
}

function phase3e3Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3e3Eligible(array $patientOverrides = []): array
{
    $user = phase3e3User('patient');
    $patient = phase3e3Patient($patientOverrides);
    phase3e3Link($patient, $user);

    return [$user, $patient];
}

function phase3e3Scan(Patient $patient, array $overrides = []): Ultrasound
{
    return Ultrasound::create(array_merge([
        'patient_id' => $patient->id,
        'scan_date' => '2026-06-10',
        'fetal_heartbeat' => 'Normal',
        'fetal_movement' => 'Normal',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'gestational_age_scan' => 18,
        'estimated_fetal_weight' => 3200.0,
    ], $overrides));
}

function phase3e3Plan(Patient $patient, array $overrides = []): BirthPlan
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

function phase3e3Outcome(Patient $patient, array $overrides = []): PregnancyOutcome
{
    return PregnancyOutcome::create(array_merge([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
    ], $overrides));
}

function phase3e3Baby(Patient $patient, array $overrides = []): Baby
{
    return Baby::create(array_merge([
        'patient_id' => $patient->id,
        'first_name' => 'Miguel',
        'middle_name' => 'Santos',
        'last_name' => 'Reyes',
        'sex' => 'Male',
        'date_of_birth' => '2026-11-10',
        'time_of_birth' => '08:30',
        'birth_weight' => 3.25,
        'birth_length' => 50.0,
    ], $overrides));
}

function phase3e3Visit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => '2026-06-01',
        'gestational_age' => 17.0,
        'bp_sys' => 118,
        'bp_dia' => 76,
        'weight' => 62.5,
        'temperature' => 36.6,
        'fundic_height' => '17 cm',
        'fetal_heart_tone' => 'Regular 140',
        'fetal_movement' => 'Active',
        'next_visit_date' => '2026-06-20',
    ], $overrides));
}

function phase3e3Page(object $test, User $user, array $expected = []): TestResponse
{
    $response = $test->actingAs($user)->get(route('patient.medical-records.index'));
    $response->assertOk();

    foreach ($expected as $needle) {
        $response->assertSee($needle);
    }

    return $response;
}

/*
|--------------------------------------------------------------------------
| 1–2. Ultrasound records
|--------------------------------------------------------------------------
*/

it('renders the patient own ultrasound records with verified fields and units', function () {
    [$user, $patient] = phase3e3Eligible();
    phase3e3Scan($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Ultrasound Records')
        ->assertSee('Jun 10, 2026')
        ->assertSee('Scan Date')
        ->assertSee('Gestational Age from Scan')
        ->assertSee('18.0 weeks')
        ->assertSee('Estimated Fetal Weight')
        ->assertSee('3200 g')
        ->assertSee('Fetal Heartbeat')
        ->assertSee('Normal')
        ->assertSee('Fetal Movement')
        ->assertSee('Presentation')
        ->assertSee('Cephalic')
        ->assertSee('Amniotic Fluid')
        ->assertSee('Placenta Position')
        ->assertSee('Posterior');
});

it('lists ultrasound records newest first with id as the tie-breaker', function () {
    [$user, $patient] = phase3e3Eligible();

    // Four-digit gram values so that no needle is ever a substring of
    // another (strpos would otherwise match "500 g" inside "2500 g").
    phase3e3Scan($patient, ['scan_date' => '2026-03-01', 'estimated_fetal_weight' => 2200.0]);
    phase3e3Scan($patient, ['scan_date' => '2026-05-01', 'estimated_fetal_weight' => 3300.0]);
    phase3e3Scan($patient, ['scan_date' => '2026-01-01', 'estimated_fetal_weight' => 1100.0]);

    // Same-day pair: the higher id must win.
    $early = phase3e3Scan($patient, ['scan_date' => '2026-07-01', 'estimated_fetal_weight' => 4400.0]);
    $late = phase3e3Scan($patient, ['scan_date' => '2026-07-01', 'estimated_fetal_weight' => 5500.0]);

    $html = phase3e3Page($this, $user)->getContent();

    $positions = [
        '5500 g' => strpos($html, '5500 g'),
        '4400 g' => strpos($html, '4400 g'),
        'Jul 01, 2026' => strpos($html, 'Jul 01, 2026'),
        '3300 g' => strpos($html, '3300 g'),
        'May 01, 2026' => strpos($html, 'May 01, 2026'),
        '2200 g' => strpos($html, '2200 g'),
        'Mar 01, 2026' => strpos($html, 'Mar 01, 2026'),
        '1100 g' => strpos($html, '1100 g'),
        'Jan 01, 2026' => strpos($html, 'Jan 01, 2026'),
    ];

    foreach ($positions as $offset) {
        expect($offset)->not->toBeFalse();
    }

    expect($positions['5500 g'])->toBeLessThan($positions['4400 g'])
        ->and($positions['Jul 01, 2026'])->toBeLessThan($positions['3300 g'])
        ->and($positions['3300 g'])->toBeLessThan($positions['2200 g'])
        ->and($positions['2200 g'])->toBeLessThan($positions['1100 g']);

    expect($late->id)->toBeGreaterThan($early->id);
});

/*
|--------------------------------------------------------------------------
| 3. Birth plan
|--------------------------------------------------------------------------
*/

it('renders the patient own birth plan with patient-friendly labels', function () {
    [$user, $patient] = phase3e3Eligible();
    phase3e3Plan($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Birth Plan')
        ->assertSee('Planned Prenatal Visits')
        ->assertSee('Plan to Deliver in This Clinic')
        ->assertSee('Planned Delivery Location')
        ->assertSee('Lying-in Clinic A')
        ->assertSee('Transportation Arrangement')
        ->assertSee('Family car')
        ->assertSee('Transportation Will Cost Money')
        ->assertSee('Planned Payment Method')
        ->assertSee('Cash')
        ->assertSee('Started Saving for Delivery')
        ->assertSee('Birth Companion')
        ->assertSee('Husband')
        ->assertSee('Caregiver at Home')
        ->assertSee('Mother')
        ->assertSee('Plan to Have More Children')
        ->assertSee('Number of More Children Planned')
        ->assertSee('Knows a Family Planning Method')
        ->assertSee('Used Family Planning Before')
        ->assertSee('Family Planning Method')
        ->assertSee('Pills')
        ->assertSee('Family Planning Method Source')
        ->assertSee('Health center');

    // Boolean answers are readable chips, not stored 0/1 values. The plan
    // contributes 4 "Yes" (deliver_in_clinic, saving_started,
    // plan_more_children, knows_fp_method) and 2 "No" (transport_cost,
    // used_fp_before); the Pregnancy Overview adds 2 more "Yes"
    // (previous_cs and miscarriage both seeded as 1).
    $flat = preg_replace('/\s+/', '', $this->actingAs($user)->get(route('patient.medical-records.index'))->getContent());
    expect(substr_count($flat, '>Yes<'))->toBe(6)
        ->and(substr_count($flat, '>No<'))->toBe(2)
        ->and($flat)->toContain('>8<')
        ->and($flat)->toContain('>2<');
});

/*
|--------------------------------------------------------------------------
| 4. Delivery records
|--------------------------------------------------------------------------
*/

it('renders the patient own delivery record with an accurate human-readable label', function () {
    [$user, $patient] = phase3e3Eligible([
        'status' => 'DELIVERED',
        'delivery_date' => '2026-11-10',
    ]);
    phase3e3Outcome($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Delivery Records')
        ->assertSee('Pregnancy Outcome')
        ->assertSee('Delivered')
        ->assertSee('Delivery Date')
        ->assertSee('Nov 10, 2026')
        ->assertSee('Delivery Location')
        ->assertSee('This Clinic');
});

/*
|--------------------------------------------------------------------------
| 5–6. Baby information
|--------------------------------------------------------------------------
*/

it('renders the patient own baby information with existing display conventions', function () {
    [$user, $patient] = phase3e3Eligible();
    phase3e3Baby($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Baby Information')
        ->assertSee('Baby 1')
        ->assertSee('Full Name')
        ->assertSee('Miguel Santos Reyes')
        ->assertSee('Sex')
        ->assertSee('Male')
        ->assertSee('Date of Birth')
        ->assertSee('Nov 10, 2026')
        ->assertSee('Time of Birth')
        ->assertSee('08:30 AM')
        ->assertSee('Birth Weight')
        ->assertSee('3.3 kg')
        ->assertSee('Birth Length')
        ->assertSee('50.0 cm');
});

it('renders multiple babies as separate numbered cards', function () {
    [$user, $patient] = phase3e3Eligible();

    phase3e3Baby($patient, [
        'first_name' => 'Twin',
        'middle_name' => 'A',
        'sex' => 'Female',
        'time_of_birth' => '08:30',
        'birth_weight' => 2.40,
    ]);
    phase3e3Baby($patient, [
        'first_name' => 'Twin',
        'middle_name' => 'B',
        'sex' => 'Male',
        'time_of_birth' => '08:32',
        'birth_weight' => 2.60,
    ]);

    $html = phase3e3Page($this, $user, [
        'Baby 1',
        'Baby 2',
        'Twin A Reyes',
        'Twin B Reyes',
        'Female',
        'Male',
        '08:30 AM',
        '08:32 AM',
        '2.4 kg',
        '2.6 kg',
    ])->getContent();

    // Each card carries the label twice (aria-label + heading), so the
    // second twin's label proves both cards rendered.
    expect(substr_count($html, 'Baby 1'))->toBe(2)
        ->and(substr_count($html, 'Baby 2'))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 7–10. Empty states
|--------------------------------------------------------------------------
*/

it('shows a clear empty state when no ultrasounds exist', function () {
    [$user] = phase3e3Eligible();

    phase3e3Page($this, $user, [
        'Ultrasound Records',
        'No ultrasound records are available for this pregnancy yet.',
    ]);
});

it('shows a clear empty state when no birth plan exists', function () {
    [$user] = phase3e3Eligible();

    phase3e3Page($this, $user, [
        'Birth Plan',
        'No birth plan has been recorded for this pregnancy yet.',
    ]);
});

it('shows a neutral empty state when no delivery record exists', function () {
    [$user] = phase3e3Eligible();

    phase3e3Page($this, $user, [
        'Delivery Records',
        'No delivery record is available for this pregnancy.',
    ]);
});

it('shows a clear empty state when no baby records exist', function () {
    [$user] = phase3e3Eligible();

    phase3e3Page($this, $user, [
        'Baby Information',
        'No baby information is available for this pregnancy yet.',
    ]);
});

/*
|--------------------------------------------------------------------------
| 11–12. Ownership boundary
|--------------------------------------------------------------------------
*/

it('never shows another patient ultrasound, birth plan, delivery or baby', function () {
    [$user] = phase3e3Eligible();

    $other = phase3e3Patient([
        'first_name' => 'Foreigner',
        'last_name' => 'Outsider',
        'birthdate' => '1990-01-01',
    ]);
    phase3e3Scan($other, ['estimated_fetal_weight' => 9999.0, 'presentation' => 'MARKER-OTHER-PRESENTATION']);
    phase3e3Plan($other, ['birth_companion' => 'MARKER-OTHER-COMPANION']);
    phase3e3Baby($other, [
        'first_name' => 'MARKEROTHERBABY',
        'birth_weight' => 9.9,
        'time_of_birth' => '23:59',
    ]);

    $html = phase3e3Page($this, $user)->getContent();

    foreach ([
        '9999',
        'MARKER-OTHER-PRESENTATION',
        'MARKER-OTHER-COMPANION',
        'MARKEROTHERBABY',
        '9.9 kg',
        '11:59 PM',
        'Foreigner',
        'Outsider',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered another patient value [$forbidden].");
    }
});

it('does not expose a name-matched previous pregnancy', function () {
    [$user, $patient] = phase3e3Eligible();

    // The row Start New Pregnancy clones away from: same mother identity,
    // already delivered, with its own scans / plan / outcome / babies.
    $prior = phase3e3Patient([
        'first_name' => $patient->first_name,
        'middle_name' => $patient->middle_name,
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate,
        'status' => 'DELIVERED',
        'delivery_date' => '2022-05-05',
        'gravida' => 2,
        'lmp' => '2021-08-08',
        'edd' => '2022-05-15',
    ]);
    phase3e3Scan($prior, ['estimated_fetal_weight' => 8888.0, 'presentation' => 'MARKER-PRIOR-PRESENTATION']);
    phase3e3Plan($prior, ['birth_companion' => 'MARKER-PRIOR-COMPANION']);
    phase3e3Outcome($prior, ['delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_HOME]);
    phase3e3Baby($prior, [
        'first_name' => 'MARKERPRIORBABY',
        'date_of_birth' => '2022-05-05',
        'birth_weight' => 8.8,
        'time_of_birth' => '11:11',
    ]);

    $html = phase3e3Page($this, $user)->getContent();

    foreach ([
        '8888',
        'MARKER-PRIOR-PRESENTATION',
        'MARKER-PRIOR-COMPANION',
        'MARKERPRIORBABY',
        '8.8 kg',
        'May 05, 2022',
        'Home',
        'Delivered',
        'Nov 10, 2026',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered a previous pregnancy value [$forbidden].");
    }

    // The linked (still ongoing) pregnancy never claims a delivery.
    expect($html)->toContain('No delivery record is available for this pregnancy.');
});

/*
|--------------------------------------------------------------------------
| 13–14. Staff-only notes, metadata and file paths are excluded
|--------------------------------------------------------------------------
*/

it('never renders staff-only notes, remarks or outcome confirmation provenance', function () {
    [$user, $patient] = phase3e3Eligible();
    $staff = phase3e3User('staff');

    phase3e3Scan($patient, ['remarks' => 'MARKER-US-REMARKS']);
    phase3e3Plan($patient, ['notes' => 'MARKER-BIRTH-PLAN-NOTES']);
    phase3e3Outcome($patient, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => '2026-11-11 09:00:00',
        'follow_up_recorded_by' => $staff->id,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'confirmed_at' => '2026-11-11 09:00:00',
        'confirmed_by' => $staff->id,
        'notes' => 'MARKER-OUTCOME-NOTES',
    ]);
    phase3e3Baby($patient);

    $html = phase3e3Page($this, $user, ['Delivered'])->getContent();

    // Stored VALUES that must never reach the page.
    foreach ([
        'MARKER-US-REMARKS',
        'MARKER-BIRTH-PLAN-NOTES',
        'MARKER-OUTCOME-NOTES',
        'STILL_PREGNANT_CONFIRMED',
        'CLINIC_RECORD',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered forbidden value [$forbidden].");
    }

    // Forbidden COLUMN / internal field names.
    foreach ([
        'remarks',
        'notes',
        'confirmation_source',
        'confirmed_at',
        'confirmed_by',
        'follow_up_status',
        'follow_up_recorded_at',
        'follow_up_recorded_by',
        'Confirmation Source',
        'Confirmed By',
        'Recorded By',
        'created_at',
        'updated_at',
        'patient_id',
        'user_id',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered forbidden field name [$forbidden].");
    }
});

it('never renders ultrasound report paths, filenames or a file-serving link', function () {
    [$user, $patient] = phase3e3Eligible();

    phase3e3Scan($patient, [
        'report_file' => 'reports/MARKER-REPORT-FILE.pdf',
        'report_image' => 'reports/MARKER-REPORT-IMAGE.jpg',
    ]);

    $html = phase3e3Page($this, $user)->getContent();

    foreach ([
        'MARKER-REPORT-FILE',
        'MARKER-REPORT-IMAGE',
        'report_file',
        'report_image',
        'ultrasound.file',
        'storage/',
        '.pdf',
        '.jpg',
        'download',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered file path or download cue [$forbidden].");
    }

    expect($html)->not->toContain('<input type="file"');
});

/*
|--------------------------------------------------------------------------
| 15. No new routes
|--------------------------------------------------------------------------
*/

it('adds no medical-record write route and no portal file-serving route', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name !== null && str_contains($name, 'medical-records'))
        ->values()
        ->all();

    expect($names)->toBe(['patient.medical-records.index']);

    // The four new sections are served by the existing GET route only — no
    // patient-namespaced ultrasound / birth-plan / baby / delivery /
    // outcome endpoint was introduced.
    $portalNames = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name !== null && str_starts_with($name, 'patient.'))
        ->filter(fn ($name) => (bool) preg_match(
            '/(ultrasound|birth-plan|bab(y|ies)|delivery|outcome)/',
            $name
        ))
        ->values()
        ->all();

    expect($portalNames)->toBe([]);
});

/*
|--------------------------------------------------------------------------
| 16. Phase 3E.2 sections still render
|--------------------------------------------------------------------------
*/

it('keeps every Phase 3E.2 section and the seven-section order intact', function () {
    [$user, $patient] = phase3e3Eligible();
    phase3e3Visit($patient);

    $html = phase3e3Page($this, $user, [
        'My Medical Records',
        'Records for your currently linked pregnancy.',
        'Pregnancy Overview',
        'Prenatal Visit History',
        'Medical History',
        'Ultrasound Records',
        'Birth Plan',
        'Delivery Records',
        'Baby Information',
        'Jun 01, 2026',
        '17.0 weeks',
        '118/76 mmHg',
    ])->getContent();

    $offset = 0;
    foreach ([
        'Pregnancy Overview',
        'Prenatal Visit History',
        'Medical History',
        'Ultrasound Records',
        'Birth Plan',
        'Delivery Records',
        'Baby Information',
    ] as $heading) {
        $position = strpos($html, $heading);
        expect($position)->not->toBeFalse();
        expect($position)->toBeGreaterThan($offset);
        $offset = $position;
    }

    // The page is still read-only: the only form is the layout logout.
    expect(substr_count($html, '<form'))->toBe(1)
        ->and($html)->toContain('action="' . route('logout') . '"')
        ->and($html)->not->toContain('method="PUT"')
        ->and($html)->not->toContain('method="PATCH"')
        ->and($html)->not->toContain('method="DELETE"');
});

/*
|--------------------------------------------------------------------------
| 17–19. Authorization matrix unchanged
|--------------------------------------------------------------------------
*/

it('still refuses unlinked and archived patient accounts', function () {
    $unlinked = phase3e3User('patient');
    $this->actingAs($unlinked)->get(route('patient.medical-records.index'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $user = phase3e3User('patient');
    $patient = phase3e3Patient();
    phase3e3Link($patient, $user);
    phase3e3Scan($patient);
    phase3e3Plan($patient);
    phase3e3Outcome($patient);
    phase3e3Baby($patient);
    $patient->delete();

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertForbidden()
        ->assertSee('Please contact the clinic for assistance.');
});

it('keeps admin and staff away from the expanded records page', function () {
    $this->actingAs(phase3e3User('admin'))
        ->get(route('patient.medical-records.index'))
        ->assertForbidden();

    $this->actingAs(phase3e3User('staff'))
        ->get(route('patient.medical-records.index'))
        ->assertForbidden();
});

it('still sends a flagged patient to the mandatory password change form', function () {
    $user = phase3e3User('patient', flagged: true);
    $patient = phase3e3Patient();
    phase3e3Link($patient, $user);
    phase3e3Scan($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

/*
|--------------------------------------------------------------------------
| Edge cases — nullable fields and formatting
|--------------------------------------------------------------------------
*/

it('treats unrecorded nullable birth plan fields as unrecorded, never as No', function () {
    // Every NULLABLE answer is NULL. The boolean columns are declared
    // NOT NULL with a 0 database default (migration 2026_03_15_154906), so
    // they legitimately keep their recorded "No" — they are never NULL.
    [$user, $patient] = phase3e3Eligible();
    phase3e3Plan($patient, [
        'planned_visits' => null,
        'number_more_children' => null,
        'delivery_location' => null,
        'transportation' => null,
        'payment_method' => null,
        'birth_companion' => null,
        'caregiver_home' => null,
        'family_planning_method' => null,
        'fp_source' => null,
    ]);

    $flat = preg_replace('/\s+/', '', phase3e3Page($this, $user)->getContent());

    // Unrecorded text/count answers are omitted as rows, not invented.
    expect($flat)->not->toContain('PlannedPrenatalVisits')
        ->and($flat)->not->toContain('NumberofMoreChildrenPlanned')
        ->and($flat)->not->toContain('PlannedDeliveryLocation')
        ->and($flat)->not->toContain('TransportationArrangement')
        ->and($flat)->not->toContain('PlannedPaymentMethod')
        ->and($flat)->not->toContain('FamilyPlanningMethodSource')
        ->and($flat)->toContain('PlantoDeliverinThisClinic');

    // A partially recorded plan shows only its recorded answers; the
    // unrecorded count is never turned into a zero.
    [$user2, $patient2] = phase3e3Eligible(['email' => 'partial@phase3e3.test']);
    phase3e3Plan($patient2, [
        'delivery_location' => 'MARKER-PLAN-LOCATION',
        'transportation' => 'MARKER-PLAN-TRANSPORT',
        'planned_visits' => null,
        'number_more_children' => null,
        'payment_method' => null,
        'birth_companion' => null,
        'caregiver_home' => null,
        'family_planning_method' => null,
        'fp_source' => null,
    ]);

    $html = phase3e3Page($this, $user2, ['MARKER-PLAN-LOCATION', 'MARKER-PLAN-TRANSPORT'])->getContent();
    $flat2 = preg_replace('/\s+/', '', $html);

    expect($flat2)->toContain('PlannedDeliveryLocation')
        ->and($flat2)->toContain('TransportationArrangement')
        ->and($flat2)->not->toContain('PlannedPrenatalVisits')
        ->and($flat2)->not->toContain('NumberofMoreChildrenPlanned')
        ->and($flat2)->not->toContain('PlannedPaymentMethod');
});

it('keeps a delivery record honest when only some facts are recorded', function () {
    // An outcome row whose fact columns are all NULL is "no observed
    // evidence yet" — it must render the empty state, never a delivery.
    [$user, $patient] = phase3e3Eligible();
    phase3e3Outcome($patient, [
        'outcome_type' => null,
        'delivery_location' => null,
    ]);

    phase3e3Page($this, $user, ['No delivery record is available for this pregnancy.']);

    // A recorded location without a recorded outcome is still shown, with
    // the outcome honestly left unrecorded.
    [$user2, $patient2] = phase3e3Eligible(['email' => 'location-only@phase3e3.test']);
    phase3e3Outcome($patient2, [
        'outcome_type' => null,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_ANOTHER_FACILITY,
    ]);

    $this->actingAs($user2)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Another Facility')
        ->assertSee('Pregnancy Outcome')
        ->assertSee('Delivery Date')
        ->assertSee('Not recorded');
});

it('formats partial ultrasound measurements honestly', function () {
    [$user, $patient] = phase3e3Eligible();

    phase3e3Scan($patient, [
        'scan_date' => '2026-07-04',
        'gestational_age_scan' => null,
        'estimated_fetal_weight' => 3150.5,
        'fetal_heartbeat' => null,
        'fetal_movement' => null,
        'presentation' => null,
        'amniotic_fluid' => null,
        'placenta_position' => null,
    ]);

    $html = phase3e3Page($this, $user, [
        '3150.5 g',
        'Jul 04, 2026',
        'Gestational Age from Scan',
        'Estimated Fetal Weight',
        'Fetal Heartbeat',
        'Not recorded',
    ])->getContent();

    expect($html)->toContain('Presentation');
});

it('keeps a guest redirected to login and clinic route authorization unchanged', function () {
    // Guest first — actingAs() persists on the app instance for the rest
    // of the test, so an unauthenticated probe must run before any login.
    $this->get(route('patient.medical-records.index'))->assertRedirect(route('login'));

    [$patientUser] = phase3e3Eligible();
    $this->actingAs($patientUser)->get(route('patients.index'))->assertForbidden();

    $staff = phase3e3User('staff');
    $this->actingAs($staff)->get(route('patients.index'))->assertOk();
});
