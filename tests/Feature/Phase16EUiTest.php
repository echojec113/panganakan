<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

function phase16eUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase16ePatient(string $firstName = 'Phase'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => '16e',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

function phase16eManualReferral(Patient $patient, User $user, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'doctor_name' => 'Dr. Cruz',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ], $overrides));
}

function phase16eLinkedVisit(): PrenatalVisit
{
    return PrenatalVisit::create([
        'patient_id' => phase16ePatient('Linka')->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 150,
        'bp_dia' => 110,
        'weight' => 60,
        'gestational_age' => 30,
        'risk_level' => 'HIGH',
        'assessment' => 'Urgent blood pressure concern',
        'recommendation' => 'Immediate specialist referral',
        'decision_source' => 'RULE_BASED',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'rule_reasons' => ['Severe-range blood-pressure finding'],
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
        'bp_assessment' => [
            'reason_code' => 'BP-URG',
            'label' => 'Severe-range blood-pressure finding',
            'urgency' => 'URGENT_CLINICAL_REVIEW',
            'status' => 'SEVERE',
        ],
        'assessment_metadata' => [
            'context' => ['assessment_date' => now()->toDateString()],
            'interaction_evidence' => [
                ['code' => 'INT-CS-PRES', 'label' => 'Previous cesarean with abnormal presentation', 'required_factor_codes' => ['CS-01', 'US-P01']],
            ],
            'versions' => ['assessment_engine' => '1.0.0', 'clinical_rules' => '1.1.0', 'context' => 1],
            'assessed_at' => '2026-08-10T10:00:00+00:00',
        ],
    ]);
}

function phase16eLinkedReferral(): Referral
{
    $user = phase16eUser();
    $visit = phase16eLinkedVisit();
    $snapshot = app(\App\Services\ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);

    $referral = Referral::create([
        'patient_id'          => $visit->patient_id,
        'prenatal_visit_id'   => $visit->id,
        'assessment_snapshot' => $snapshot,
        'created_by'          => $user->id,
        'referred_to'         => 'Provincial Hospital',
        'doctor_name'         => 'Dr. Cruz',
        'reason'              => 'Needs specialist care',
        'notes'               => 'Linked referral',
        'referral_date'       => now()->toDateString(),
        'status'              => 'Pending',
    ]);

    return $referral;
}

// ---------------------------------------------------------------------------
// A. Statuses on index
// ---------------------------------------------------------------------------

it('A: index renders all four referral statuses with their visual badges', function () {
    $user = phase16eUser();
    $patient = phase16ePatient();

    $pending = phase16eManualReferral($patient, $user);
    $completed = phase16eManualReferral($patient, $user, ['status' => 'Completed', 'completed_at' => now()]);
    $refused = phase16eManualReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Patient declined after counseling.',
        'waiver_signed' => true,
    ]);
    $cancelled = phase16eManualReferral($patient, $user, ['status' => 'Cancelled']);

    $response = $this->actingAs($user)->get(route('referrals.index'));

    $response->assertOk();
    $response->assertSeeText($patient->first_name);
    $response->assertSeeText('Pending');
    $response->assertSeeText('Completed');
    $response->assertSeeText('Refused');
    $response->assertSeeText('Cancelled');
    expect($response->getContent())->toContain('View Referral');
    expect(substr_count($response->getContent(), 'pending'))->toBeGreaterThanOrEqual(1);
});

// ---------------------------------------------------------------------------
// A2. Index presentation: rows carry only View/Print, no per-row mutations
// ---------------------------------------------------------------------------

it('A2: index row does not expose per-row Complete/Refocus/Cancel mutation actions', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Indx');
    phase16eManualReferral($patient, $user);

    $response = $this->actingAs($user)->get(route('referrals.index'));

    $response->assertOk();
    expect($response->getContent())->toContain('View Referral');
    expect($response->getContent())->not->toContain('Mark Completed');
    expect($response->getContent())->not->toContain('Record Refusal');
    expect($response->getContent())->not->toContain('Cancel Referral');
});

// ---------------------------------------------------------------------------
// B. Assessment-linked labels
// ---------------------------------------------------------------------------

it('B: index labels an assessment-linked referral without exposing the visit id as the main label', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $response = $this->actingAs($user)->get(route('referrals.index'));

    $response->assertOk();
    $response->assertSee('Assessment-linked');
    $response->assertDontSee('prenatal_visit_id');
});

it('B2: detail page shows "Assessment-linked" for a linked referral', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Assessment-linked');
    $response->assertSeeText('Assessment Evidence at Referral');
});

// ---------------------------------------------------------------------------
// C. Manual non-fabrication
// ---------------------------------------------------------------------------

it('C: manual referral detail renders cleanly as "Manual Referral" without inventing evidence', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Manui');
    $referral = phase16eManualReferral($patient, $user);

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Manual Referral');
    $response->assertSee('No linked prenatal assessment record');
    $response->assertDontSee('Assessment Evidence at Referral');
    $response->assertDontSee('HIGH');
});

it('C2: manual referral index shows the Manual Referral source', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Manu');
    phase16eManualReferral($patient, $user);

    $response = $this->actingAs($user)->get(route('referrals.index'));

    $response->assertOk();
    $response->assertSee('Manual Referral');
    $response->assertDontSee('Assessment-linked');
});

// ---------------------------------------------------------------------------
// D. Refusal / Completed / Cancelled display
// ---------------------------------------------------------------------------

it('D: refused referral shows the recorded date, recorded-by fallback, notes, and waiver status', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Rafu');
    $recorder = User::factory()->create(['role' => 'staff', 'name' => 'Nurse Tess']);
    $referral = phase16eManualReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $recorder->id,
        'refusal_notes' => 'The patient declined the specialist referral after counseling.',
        'waiver_signed' => true,
    ]);

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Referral Refusal Record');
    $response->assertSee('is not set because the referral was refused rather than completed.');
    $response->assertSeeText('Nurse Tess');
    $response->assertSeeText('Physical waiver signed/recorded');
    $response->assertSeeText('Yes');
    $response->assertSeeText('The patient declined the specialist referral after counseling.');
});

it('D2: completed referral shows the recorded completed_on without a hospital-acceptance claim', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Comp');
    $referral = phase16eManualReferral($patient, $user, [
        'status' => 'Completed',
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Clinic staff recorded the referral follow-through as completed.');
    $response->assertDontSeeText('admitted');
    $response->assertDontSeeText('accepted');
});

it('D3: cancelled referral shows no mutation actions and no fake cancellation narrative', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Cancel');
    $referral = phase16eManualReferral($patient, $user, [
        'notes' => 'Original referral note.',
        'status' => 'Cancelled',
    ]);

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Cancelled Referral');
    $response->assertSeeText('Original referral note.');
    $response->assertDontSeeText('Mark Completed');
    $response->assertDontSeeText('Record Refusal');
    $response->assertDontSeeText('Cancel Referral');
});

// ---------------------------------------------------------------------------
// E. Pending assets + F. closed hides actions
// ---------------------------------------------------------------------------

it('E: pending referral shows all three mutation actions on the detail page', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Pend');
    $referral = phase16eManualReferral($patient, $user);

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSee('Mark Completed');
    $response->assertSee('Record Refusal');
    $response->assertSee('Cancel Referral');
});

it('F: closed referrals do not expose mutation actions on the index', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Close');
    phase16eManualReferral($patient, $user, ['status' => 'Completed', 'completed_at' => now()]);
    phase16eManualReferral($patient, $user, ['status' => 'Refused', 'refusal_recorded_at' => now(), 'refusal_recorded_by' => $user->id, 'refusal_notes' => 'Patient declined.' ]);
    phase16eManualReferral($patient, $user, ['status' => 'Cancelled']);

    $response = $this->actingAs($user)->get(route('referrals.index'));

    // Closed rows must not offer mutation forms.
    $response->assertOk();
    $content = $response->getContent();
    $hasMutation = str_contains($content, 'Mark Completed') || str_contains($content, 'Record Refusal') || str_contains($content, 'Cancel Referral');
    expect($hasMutation)->toBeFalse();
});

it('F2: blocked mutation routes stay authoritative for closed referrals (detail actions hidden)', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Ftwo');
    $referral = phase16eManualReferral($patient, $user, ['status' => 'Refused', 'refusal_recorded_at' => now(), 'refusal_recorded_by' => $user->id, 'refusal_notes' => 'Patient declined.']);

    $this->actingAs($user)->post(route('referrals.complete', $referral->id))
        ->assertSessionHas('error');
    $referral->refresh();
    expect($referral->status)->toBe('Refused');
});

// ---------------------------------------------------------------------------
// G. Snapshot finger evidence for detail + print (historical contract)
// ---------------------------------------------------------------------------

it('G: detail page shows a frozen snapshot with factors, interactions, BP urgency, and versions', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSee('URGENT CLINICAL REVIEW');
    $response->assertSeeText('Previous cesarean section');
    $response->assertSeeText('Previous cesarean with abnormal presentation');
    $response->assertSeeText('Urgent blood-pressure finding captured in this assessment.');
    $response->assertSeeText('Clinical Rules Version: 1.1.0');
    $response->assertSeeText('Assessment Engine Version: 1.0.0');
});

it('G2: fingerprint shows visit date, assessment date, and decision source from the snapshot', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $response = $this->actingAs($user)->get(route('referrals.show', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Visit Date');
    $response->assertSeeText('Decision Source');
    $response->assertSeeText('Rule-Based Clinical Assessment');
});

// ---------------------------------------------------------------------------
// H. Pending duplicate UX on patient profile
// ---------------------------------------------------------------------------

it('H: profile replaces the create button with a pending referral link when a pending referral exists', function () {
    $user = phase16eUser();
    $visit = phase16eLinkedVisit();

    // First referral for this assessment.
    $this->actingAs($user)->post(route('referrals.store'), [
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'date_referred' => now()->toDateString(),
    ])->assertRedirect();

    $response = $this->actingAs($user)->get(route('patients.show', $visit->patient_id));

    $response->assertOk();
    $response->assertSeeText('Pending Referral Exists');
    $response->assertDontSeeText('Create Referral from this Assessment');

    // Duplicate POST is still rejected by the backend.
    $this->actingAs($user)->post(route('referrals.store'), [
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Duplicate',
        'date_referred' => now()->toDateString(),
    ])->assertSessionHasErrors('prenatal_visit_id');

    expect(Referral::where('prenatal_visit_id', $visit->id)->count())->toBe(1);
});

it('H2: profile keeps the create button when no pending referral exists yet', function () {
    $user = phase16eUser();
    $visit = phase16eLinkedVisit();

    $response = $this->actingAs($user)->get(route('patients.show', $visit->patient_id));

    $response->assertOk();
    $response->assertSeeText('Create Referral from this Assessment');
    $response->assertDontSeeText('Pending Referral Exists');
});

// ---------------------------------------------------------------------------
// I. Risk monitoring simultaneous indicator
// ---------------------------------------------------------------------------

it('I: risk monitoring shows Pending Referral and Overdue simultaneously', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Overdue');
    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Preeclampsia'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->subDays(3)->toDateString(),
    ]);

    phase16eManualReferral($patient, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertOk()
        ->assertSeeText('Pending Referral')
        ->assertSeeText('Overdue');
});

// ---------------------------------------------------------------------------
// D3. Create page — read-only assessment presentation
// ---------------------------------------------------------------------------

it('D3: linked create page shows the read-only assessment summary panel', function () {
    $user = phase16eUser();
    $visit = phase16eLinkedVisit();

    $response = $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertOk();
    $response->assertSeeText('Assessment Being Referred');
    $response->assertSee('Linked Assessment Evidence (read-only)');
});

// ---------------------------------------------------------------------------
// I2. Patient profile does not duplicate the full snapshot evidence
// ---------------------------------------------------------------------------

it('I2: patient profile does not duplicate the full referral snapshot evidence', function () {
    $user = phase16eUser();
    $visit = phase16eLinkedVisit();
    $snapshot = app(\App\Services\ReferralAssessmentSnapshotService::class)->fromPrenatalVisit($visit);
    Referral::create([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'assessment_snapshot' => $snapshot,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $content = $this->actingAs($user)->get(route('patients.show', $visit->patient_id))->getContent();

    expect($content)->toContain('Latest Referral');
    expect(substr_count($content, 'Assessment Evidence at Referral'))->toBe(0);
});

// ---------------------------------------------------------------------------
// J, K, L. Print view
// ---------------------------------------------------------------------------

it('J: print shows snapshot evidence only and is not affected by later live-visit changes', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $before = $this->actingAs($user)->get(route('referrals.print', $referral->id));
    $before->assertOk();
    $before->assertSeeText('Assessment Evidence at Referral');
    $before->assertSeeText('Previous cesarean section');
    $before->assertSee('URGENT CLINICAL REVIEW');
    $before->assertSeeText('Urgent blood-pressure finding captured in this assessment.');

    // Change the live visit; the print snapshot must NOT change.
    $referral->prenatalVisit()->update([
        'risk_level' => 'LOW',
        'urgency' => null,
        'assessment' => 'Rewritten after referral',
    ]);

    $after = $this->actingAs($user)->get(route('referrals.print', $referral->id));
    $after->assertOk();
    $after->assertSeeText('Assessment Evidence at Referral');
    $after->assertSee('URGENT CLINICAL REVIEW');
    $after->assertDontSee('Rewritten after referral');
    $after->assertSeeText('Urgent blood pressure concern');
    $after->assertSee('Previous cesarean section');
});

it('K: print of a manual referral renders cleanly without evidence fabrication', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Kman');
    $referral = phase16eManualReferral($patient, $user, ['reason' => 'Monitored care needed']);

    $response = $this->actingAs($user)->get(route('referrals.print', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Manual Referral');
    $response->assertDontSee('Assessment Evidence at Referral');
    $response->assertDontSee('URGENT CLINICAL REVIEW');
});

it('L: print includes the refusal record with recorded date, recorder, and waiver for refused referrals', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Lref');
    $recorder = User::factory()->create(['role' => 'staff', 'name' => 'Nurse Lia']);
    $referral = phase16eManualReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $recorder->id,
        'refusal_notes' => 'Patient declined the referral after counseling.',
        'waiver_signed' => true,
    ]);

    $response = $this->actingAs($user)->get(route('referrals.print', $referral->id));

    $response->assertOk();
    $response->assertSeeText('Refusal Recorded On');
    $response->assertSeeText('Refusal Recorded By');
    $response->assertSeeText('Nurse Lia');
    $response->assertSeeText('Signed / recorded');
    expect($response->getContent())->not->toContain('digitally signed');
});

// ---------------------------------------------------------------------------
// M. NULL / malformed safe
// ---------------------------------------------------------------------------

it('M: malformed snapshot arrays render without crashing', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Mal');
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'assessment' => 'High risk',
    ]);
    $referral = Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
        'prenatal_visit_id' => $visit->id,
        'assessment_snapshot' => [
            'schema_version' => '1.0.0',
            'prenatal_visit_id' => $visit->id,
            'visit_date' => '2026-07-01',
            'risk_level' => 'HIGH',
            'factor_evidence' => ['not-an-array'],
            'interaction_evidence' => null,
            'bp_assessment' => 'not-an-array',
            'versions' => [],
        ],
    ]);

    expect(fn () => $this->actingAs($user)->get(route('referrals.show', $referral->id)))
        ->not->toThrow(Exception::class);
    $detail = $this->actingAs($user)->get(route('referrals.show', $referral->id));
    $detail->assertOk();

    $print = $this->actingAs($user)->get(route('referrals.print', $referral->id));
    $print->assertOk();
});

it('N: deleted recorder account shows a neutral staff-name fallback on detail and print', function () {
    $user = phase16eUser();
    $patient = phase16ePatient('Nd');
    $recorder = User::factory()->create(['role' => 'staff', 'name' => 'Gone Staff']);
    $referral = phase16eManualReferral($patient, $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $recorder->id,
        'refusal_notes' => 'Patient declined.',
        'waiver_signed' => false,
    ]);

    $recorderId = $recorder->id;
    $recorder->delete();

    $detail = $this->actingAs($user)->get(route('referrals.show', $referral->id));
    $detail->assertOk();
    $detail->assertSeeText('Staff account no longer available');
    $detail->assertDontSeeText('Gone Recru');

    $print = $this->actingAs($user)->get(route('referrals.print', $referral->id));
    $print->assertOk();
    $print->assertSeeText('Staff account no longer available');
});

// ---------------------------------------------------------------------------
// O. No raw JSON / internal keys in referral UI or print
// ---------------------------------------------------------------------------

it('O: referral index, detail and print never leak raw snapshot JSON or internal array keys', function () {
    $user = phase16eUser();
    $referral = phase16eLinkedReferral();

    $index = $this->actingAs($user)->get(route('referrals.index'));
    $detail = $this->actingAs($user)->get(route('referrals.show', $referral->id));
    $print = $this->actingAs($user)->get(route('referrals.print', $referral->id));

    foreach ([$index->getContent(), $detail->getContent(), $print->getContent()] as $content) {
        expect($content)->not->toContain('assessment_snapshot');
        expect($content)->not->toContain('factor_evidence');
        expect($content)->not->toContain('interaction_evidence');
        expect($content)->not->toContain('observed_context');
        expect($content)->not->toContain('decision_trace');
        expect($content)->not->toContain('data_quality_flags');
        expect($content)->not->toContain('"code":');
        expect($content)->not->toContain('{&quot;');
    }
});

// ---------------------------------------------------------------------------
// P. No new diagnosis / treatment / hospital-acceptance claims
// ---------------------------------------------------------------------------

it('P: referral UI and print do not introduce forbidden diagnosis or acceptance vocabulary', function () {
    $referral = phase16eLinkedReferral();
    $user = phase16eUser();

    $detail = $this->actingAs($user)->get(route('referrals.show', $referral->id));
    $print = $this->actingAs($user)->get(route('referrals.print', $referral->id));
    $index = $this->actingAs($user)->get(route('referrals.index'));

    foreach ([$detail->getContent(), $print->getContent(), $index->getContent()] as $content) {
        expect($content)->not->toContain('VERY HIGH');
        expect($content)->not->toContain('EXTREME');
        expect($content)->not->toContain('risk score');
        expect($content)->not->toContain('Hospital accepted');
        expect($content)->not->toContain('digitally signed');
        expect($content)->not->toContain('consent legally completed');
    }
});

// ---------------------------------------------------------------------------
// Route accessibility (admin & staff can view the new detail page)
// ---------------------------------------------------------------------------

it('X: referral detail route is reachable by admin and staff viewing roles', function () {
    $user = phase16eUser();
    $referral = phase16eLinkedReferral();

    $staff = phase16eUser();
    $this->actingAs($staff)->get(route('referrals.show', $referral->id))->assertOk();

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('referrals.show', $referral->id))->assertOk();
});