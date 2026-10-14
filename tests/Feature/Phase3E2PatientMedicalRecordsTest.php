<?php

use App\Http\Controllers\PatientMedicalRecordsController;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phase 3E.2 — Patient Portal "My Medical Records" (read-only)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the new GET route,
| ownership (no ID from anywhere), the full authorization matrix,
| column-ordered rendering, the internal-field exclusion list, the absence
| of any medical-record write route, and regression probes for the rest of
| the Patient Portal after the dashboard placeholder became a real link.
*/

function phase3e2User(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase3e2Patient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 28,
        'birthdate' => '1998-04-12',
        'email' => 'maria.records@phase3e2.test',
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'lmp' => '2026-01-15',
        'edd' => '2026-10-22',
        'previous_cs' => 1,
        'miscarriage' => 1,
    ], $overrides));
}

function phase3e2Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3e2Eligible(array $patientOverrides = []): array
{
    $user = phase3e2User('patient');
    $patient = phase3e2Patient($patientOverrides);
    phase3e2Link($patient, $user);

    return [$user, $patient];
}

function phase3e2Visit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => '2026-06-01',
        'gestational_age' => 20.0,
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 65.5,
        'temperature' => 36.5,
        'fundic_height' => '20 cm',
        'fetal_heart_tone' => 'Regular 120-160',
        'fetal_movement' => 'Active',
        'next_visit_date' => '2026-06-15',
    ], $overrides));
}

function phase3e2History(Patient $patient, array $overrides = []): MedicalHistory
{
    return MedicalHistory::create(array_merge([
        'patient_id' => $patient->id,
        'epilepsy' => true,
        'diabetes' => true,
        'smoking' => false,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| 1–4. Own-record rendering
|--------------------------------------------------------------------------
*/

it('redirects a guest to login', function () {
    $this->get(route('patient.medical-records.index'))
        ->assertRedirect(route('login'));
});

it('renders the patient own pregnancy overview', function () {
    [$user] = phase3e2Eligible();

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('My Medical Records')
        ->assertSee('Records for your currently linked pregnancy.')
        ->assertSee('Pregnancy Overview')
        ->assertSee('Pregnancy Status')
        ->assertSee('Last Menstrual Period (LMP)')
        ->assertSee('Estimated Due Date (EDD)')
        ->assertSee('Gravida')
        ->assertSee('Para')
        ->assertSee('Previous Cesarean Section History')
        ->assertSee('Miscarriage History')
        // Existing portal date convention (M d, Y).
        ->assertSee('Jan 15, 2026')
        ->assertSee('Oct 22, 2026')
        // Existing fields are never invented: an unrecorded value is "Not recorded".
        ->assertSee('ONGOING');
});

it('renders the patient own prenatal visit history with approved observation fields', function () {
    [$user, $patient] = phase3e2Eligible();
    phase3e2Visit($patient);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Prenatal Visit History')
        ->assertSee('Jun 01, 2026')
        ->assertSee('20.0 weeks')
        ->assertSee('120/80 mmHg')
        ->assertSee('65.5 kg')
        ->assertSee('36.5 °C')
        ->assertSee('20 cm')
        ->assertSee('Regular 120-160')
        ->assertSee('Active')
        ->assertSee('Jun 15, 2026');
});

it('renders the patient own medical history as readable Yes/No values', function () {
    [$user, $patient] = phase3e2Eligible();
    phase3e2History($patient, ['other_specify' => 'Old ankle fracture']);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('Medical History')
        ->assertSee('Chronic & Background Conditions')
        ->assertSee('Lifestyle, History & Physical Findings')
        ->assertSee('Epilepsy')
        ->assertSee('Diabetes')
        ->assertSee('Smoking')
        ->assertSee('Other Recorded Condition')
        ->assertSee('Old ankle fracture');

    // Yes and No both render as readable chips (whitespace-insensitive).
    // Two "Yes" come from this history fixture (epilepsy + diabetes); the
    // other two are the Pregnancy Overview rows seeded by phase3e2Patient
    // (previous_cs and miscarriage). The 16 "No" chips are the untouched
    // conditions of the 18-condition list.
    $flat = preg_replace('/\s+/', '', $this->actingAs($user)->get(route('patient.medical-records.index'))->getContent());
    expect(substr_count($flat, '>Yes<'))->toBe(4)
        ->and(substr_count($flat, '>No<'))->toBe(16);
});

/*
|--------------------------------------------------------------------------
| 5. Another patient's records never appear
|--------------------------------------------------------------------------
*/

it('never shows another patient records or a name-matched previous pregnancy', function () {
    [$user, $patient] = phase3e2Eligible();
    phase3e2Visit($patient, ['weight' => 61.0]);

    // Unrelated patient.
    $other = phase3e2Patient([
        'first_name' => 'Foreigner',
        'last_name' => 'Outsider',
        'birthdate' => '1990-01-01',
        'gravida' => 9,
        'lmp' => '1999-09-09',
        'edd' => '1999-06-16',
    ]);
    phase3e2Visit($other, ['weight' => 99.9, 'fundic_height' => 'ZZFUNDALMARKER']);
    phase3e2History($other, ['other_specify' => 'ZEBRAHISTORYMARKER']);

    // Same mother, earlier pregnancy (the row Start New Pregnancy clones
    // away from). Phase 3E.2 must NOT surface it: no name/birthdate
    // matching is performed, only the linked row is read.
    $prior = phase3e2Patient([
        'first_name' => $patient->first_name,
        'middle_name' => $patient->middle_name,
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate,
        'status' => 'DELIVERED',
        'delivery_date' => '2022-05-05',
        'gravida' => 1,
        'lmp' => '2021-08-08',
        'edd' => '2022-05-15',
    ]);
    phase3e2Visit($prior, ['weight' => 88.8]);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('61.0 kg')
        ->assertDontSee('99.9')
        ->assertDontSee('ZZFUNDALMARKER')
        ->assertDontSee('ZEBRAHISTORYMARKER')
        ->assertDontSee('88.8')
        ->assertDontSee('Sep 09, 1999')
        ->assertDontSee('Aug 08, 2021')
        ->assertDontSee('Foreigner')
        ->assertDontSee('Outsider')
        ->assertDontSee('DELIVERED');
});

it('ignores any patient id supplied by the request', function () {
    [$user, $patient] = phase3e2Eligible();
    $other = phase3e2Patient(['first_name' => 'Foreigner', 'last_name' => 'Outsider']);

    $this->actingAs($user)
        ->get(route('patient.medical-records.index') . '?patient_id=' . $other->id)
        ->assertOk()
        ->assertSee('Jan 15, 2026')
        ->assertDontSee('Foreigner');

    // There is no ID-based medical-records URL to reach.
    $this->actingAs($user)->get('/patient/medical-records/' . $other->id)->assertNotFound();
    $this->actingAs($user)->get('/patient/medical-records/' . $patient->id)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 6–9. Authorization matrix
|--------------------------------------------------------------------------
*/

it('refuses an unlinked patient account', function () {
    $user = phase3e2User('patient');

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');
});

it('refuses an archived patient account', function () {
    $user = phase3e2User('patient');
    $patient = phase3e2Patient();
    phase3e2Link($patient, $user);
    $patient->delete();

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertForbidden()
        ->assertSee('Please contact the clinic for assistance.');
});

it('keeps admin and staff away from the patient-only records page', function () {
    $this->actingAs(phase3e2User('admin'))
        ->get(route('patient.medical-records.index'))
        ->assertForbidden();

    $this->actingAs(phase3e2User('staff'))
        ->get(route('patient.medical-records.index'))
        ->assertForbidden();
});

it('sends a flagged patient to the mandatory password change form', function () {
    $user = phase3e2User('patient', flagged: true);
    $patient = phase3e2Patient();
    phase3e2Link($patient, $user);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

/*
|--------------------------------------------------------------------------
| 10–11. Empty state and ordering
|--------------------------------------------------------------------------
*/

it('renders honest empty states when nothing is recorded', function () {
    [$user, $patient] = phase3e2Eligible([
        'lmp' => null,
        'edd' => null,
        'gravida' => null,
        'para' => null,
        'previous_cs' => null,
        'miscarriage' => null,
    ]);

    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('No prenatal visits are recorded for this pregnancy yet.')
        ->assertSee('No medical history has been recorded for this pregnancy yet.')
        ->assertSee('Not recorded');

    // A missing value is never manufactured into a negative answer: the six
    // nullable overview fields all render as "Not recorded".
    $html = $this->actingAs($user)->get(route('patient.medical-records.index'))->getContent();
    expect(substr_count($html, 'Not recorded'))->toBe(6);

    expect($patient->fresh()->lmp)->toBeNull();
});

it('lists prenatal visits newest first with id as the tie-breaker', function () {
    [$user, $patient] = phase3e2Eligible();

    phase3e2Visit($patient, ['visit_date' => '2026-01-10', 'weight' => 60.0]);
    phase3e2Visit($patient, ['visit_date' => '2026-03-05', 'weight' => 64.0]);
    phase3e2Visit($patient, ['visit_date' => '2026-02-20', 'weight' => 62.0]);

    // Same-date pair: the higher id must win.
    $early = phase3e2Visit($patient, ['visit_date' => '2026-04-01', 'weight' => 66.0]);
    $late = phase3e2Visit($patient, ['visit_date' => '2026-04-01', 'weight' => 67.0]);

    $html = $this->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk()
        ->getContent();

    $positions = [
        '67.0 kg' => strpos($html, '67.0 kg'),
        '66.0 kg' => strpos($html, '66.0 kg'),
        'Apr 01, 2026' => strpos($html, 'Apr 01, 2026'),
        '64.0 kg' => strpos($html, '64.0 kg'),
        'Mar 05, 2026' => strpos($html, 'Mar 05, 2026'),
        '62.0 kg' => strpos($html, '62.0 kg'),
        'Feb 20, 2026' => strpos($html, 'Feb 20, 2026'),
        '60.0 kg' => strpos($html, '60.0 kg'),
        'Jan 10, 2026' => strpos($html, 'Jan 10, 2026'),
    ];

    foreach ($positions as $needle => $offset) {
        expect($offset)->not->toBeFalse();
    }

    expect($positions['67.0 kg'])->toBeLessThan($positions['66.0 kg'])
        ->and($positions['Apr 01, 2026'])->toBeLessThan($positions['64.0 kg'])
        ->and($positions['64.0 kg'])->toBeLessThan($positions['62.0 kg'])
        ->and($positions['62.0 kg'])->toBeLessThan($positions['60.0 kg']);

    expect($late->id)->toBeGreaterThan($early->id);
});

/*
|--------------------------------------------------------------------------
| 12. Risk, ML, notes and internal fields never render
|--------------------------------------------------------------------------
*/

it('never renders risk, ML, notes or other internal clinical fields', function () {
    [$user, $patient] = phase3e2Eligible();
    phase3e2History($patient);

    phase3e2Visit($patient, [
        'risk_level' => 'HIGH',
        'risk_reasons' => ['MARKER-RISK-REASON'],
        'decision_source' => 'MARKER-DECISION-SOURCE',
        'missing_records' => ['MARKER-MISSING-RECORD'],
        'rule_reasons' => ['MARKER-RULE-REASON'],
        'ml_prediction' => 'MARKER-ML-PREDICTION',
        'ml_valid' => true,
        'assessment' => 'MARKER-INTERNAL-ASSESSMENT',
        'recommendation' => 'MARKER-INTERNAL-RECOMMENDATION',
        'treatment_plan' => 'MARKER-INTERNAL-TREATMENT-PLAN',
        'notes' => 'MARKER-INTERNAL-NOTES',
        'repeat_bp_sys' => 130,
        'repeat_bp_dia' => 90,
        'bp_verification_status' => 'verified',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_assessment' => ['marker' => 'MARKER-BP-ASSESSMENT'],
        'factor_evidence' => ['MARKER-FACTOR-EVIDENCE'],
        'assessment_metadata' => ['marker' => 'MARKER-ASSESSMENT-METADATA'],
        'hypertension' => true,
        'diabetes' => true,
        'anemia' => true,
        'presenting_part' => 'Cephalic',
        'uterine_activity' => 'Regular',
        'cervical_dilation' => '3 cm',
        'bag_of_water' => 'Intact',
    ]);

    $html = $this->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk()
        ->getContent();

    // Distinctive stored VALUES.
    foreach ([
        'MARKER-RISK-REASON',
        'MARKER-DECISION-SOURCE',
        'MARKER-MISSING-RECORD',
        'MARKER-RULE-REASON',
        'MARKER-ML-PREDICTION',
        'MARKER-INTERNAL-ASSESSMENT',
        'MARKER-INTERNAL-RECOMMENDATION',
        'MARKER-INTERNAL-TREATMENT-PLAN',
        'MARKER-INTERNAL-NOTES',
        'MARKER-BP-ASSESSMENT',
        'MARKER-FACTOR-EVIDENCE',
        'MARKER-ASSESSMENT-METADATA',
        'URGENT_CLINICAL_REVIEW',
        'Cephalic',
        'Intact',
        '3 cm',
        '130/90',
        'HIGH',
        'verified',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered forbidden value [$forbidden].");
    }

    // Forbidden COLUMN / internal field names.
    foreach ([
        'risk_level',
        'risk_reasons',
        'ml_prediction',
        'ml_valid',
        'factor_evidence',
        'assessment_metadata',
        'decision_source',
        'rule_reasons',
        'missing_records',
        'urgency',
        'bp_assessment',
        'bp_verification_status',
        'repeat_bp_recorded_by',
        'repeat_bp_sys',
        'treatment_plan',
        'created_at',
        'updated_at',
        'patient_id',
        'user_id',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden, "Rendered forbidden field name [$forbidden].");
    }

    // Approved observations still render — the allow-list is not a filter
    // that hides the whole page.
    expect($html)->toContain('120/80 mmHg')
        ->toContain('65.5 kg')
        ->toContain('Jun 01, 2026');
});

/*
|--------------------------------------------------------------------------
| 13. No medical-record write routes
|--------------------------------------------------------------------------
*/

it('exposes exactly one medical-record route and it is GET only', function () {
    $names = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->getName())
        ->filter(fn ($name) => $name !== null && str_contains($name, 'medical-records'))
        ->values()
        ->all();

    expect($names)->toBe(['patient.medical-records.index']);

    $route = Route::getRoutes()->getByName('patient.medical-records.index');
    expect($route->methods())->toBe(['GET', 'HEAD'])
        ->and($route->uri())->toBe('patient/medical-records');

    $declared = collect((new ReflectionClass(PatientMedicalRecordsController::class))
        ->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn ($method) => $method->getDeclaringClass()->getName() === PatientMedicalRecordsController::class)
        ->map(fn ($method) => $method->getName())
        ->values()
        ->all();

    expect($declared)->toBe(['index']);

    // The URI accepts no write verb.
    $this->get('/patient/medical-records')->assertRedirect(route('login'));
});

it('refuses write verbs on the medical-records URI', function () {
    [$user] = phase3e2Eligible();

    $this->actingAs($user)->post('/patient/medical-records')->assertStatus(405);
    $this->actingAs($user)->put('/patient/medical-records')->assertStatus(405);
    $this->actingAs($user)->patch('/patient/medical-records')->assertStatus(405);
    $this->actingAs($user)->delete('/patient/medical-records')->assertStatus(405);
});

/*
|--------------------------------------------------------------------------
| 14–15. Dashboard link + portal regression
|--------------------------------------------------------------------------
*/

it('links the dashboard Medical Records card to the working page', function () {
    [$user] = phase3e2Eligible();

    $html = $this->actingAs($user)->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('href="' . route('patient.medical-records.index') . '"', false)
        ->assertSee('My Medical Records')
        ->assertDontSee('Coming Soon')
        ->assertDontSee('Medical record access will be available soon.')
        ->assertDontSee('medical records are coming soon')
        ->getContent();

    // The dashboard is still a landing page, not a records page.
    expect($html)->not->toContain('Prenatal Visit History');

    // Following the card lands on the records page.
    $this->actingAs($user)->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertSee('My Medical Records')
        ->assertSee('Records for your currently linked pregnancy.');
});

it('keeps the existing Patient Portal routes functional and unchanged', function () {
    [$user] = phase3e2Eligible();

    foreach ([
        'patient.portal.coming-soon',
        'patient.profile.show',
        'patient.appointments.index',
        'patient.notifications.index',
    ] as $routeName) {
        $this->actingAs($user)->get(route($routeName))
            ->assertOk()
            ->assertDontSee('sidebar-nav')
            ->assertDontSee('Audit Logs');
    }

    // Navigation still carries every pre-existing portal destination.
    $html = $this->actingAs($user)->get(route('patient.portal.coming-soon'))->getContent();

    foreach ([
        'patient.portal.coming-soon',
        'patient.appointments.index',
        'patient.notifications.index',
        'patient.profile.show',
        'patient.medical-records.index',
    ] as $routeName) {
        expect($html)->toContain('href="' . route($routeName) . '"');
    }
});

it('presents no edit, add or delete action on the records page', function () {
    [$user, $patient] = phase3e2Eligible();
    phase3e2Visit($patient);

    $html = $this->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk()
        ->assertDontSee('Edit')
        ->assertDontSee('Delete')
        ->assertDontSee('Add Visit')
        ->assertDontSee('Update')
        ->assertDontSee('method="PUT"')
        ->assertDontSee('method="PATCH"')
        ->assertDontSee('method="DELETE"')
        ->getContent();

    // The only form on the page is the shared layout's POST logout.
    expect(substr_count($html, '<form'))->toBe(1)
        ->and($html)->toContain('action="' . route('logout') . '"');
});

it('keeps existing clinic route authorization unchanged', function () {
    // Guest first — actingAs() persists on the app instance for the rest
    // of the test, so an unauthenticated probe must run before any login.
    $this->get(route('patients.index'))->assertRedirect(route('login'));

    // Patient accounts stay inside the portal boundary.
    [$patientUser] = phase3e2Eligible();
    $this->actingAs($patientUser)->get(route('patients.index'))->assertForbidden();

    // Clinic staff keep their existing access to the staff-only list.
    $staff = phase3e2User('staff');
    $this->actingAs($staff)->get(route('patients.index'))->assertOk();
});
