<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

function linkedPatient(string $firstName = 'Delia'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

function linkedVisit(array $overrides = []): PrenatalVisit
{
    $patient = linkedPatient();

    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
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
    ], $overrides));
}

function referralPayload(array $overrides = []): array
{
    return array_merge([
        'patient_id' => linkedPatient()->id,
        'referred_to' => 'Provincial Hospital',
        'doctor_name' => 'Dr. Cruz',
        'reason' => 'Needs specialist care',
        'notes' => 'Linked referral',
        'date_referred' => now()->toDateString(),
    ], $overrides);
}

it('shows the read-only assessment preview for a linked HIGH create page', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $response = $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertOk();
    $response->assertSee('Linked Assessment Evidence (read-only)');
    $response->assertSee('URGENT CLINICAL REVIEW');
    $response->assertSee('Previous cesarean section');
    $response->assertSee('Previous cesarean with abnormal presentation');
    $response->assertSee('Previous cesarean with abnormal presentation; Previous cesarean section; Severe-range blood-pressure finding');
});

it('rejects the create page for a LOW-risk visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit(['risk_level' => 'LOW', 'urgency' => null]);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertStatus(403);
});

it('rejects the create page for an ASSESSMENT INCOMPLETE visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit(['risk_level' => 'ASSESSMENT INCOMPLETE']);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertStatus(403);
});

it('rejects the create page when the visit belongs to another patient', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $other = linkedPatient('Otria');

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $other->id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertStatus(403);
});

it('rejects the create page for a soft-deleted visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $visit->delete();

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertStatus(404);
});

it('redirects delivered patients away from the linked create page', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $visit->patient->update(['status' => 'DELIVERED']);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertRedirect();
});

it('creates an assessment-linked referral with the persisted visit id', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->patient_id)->toBe($visit->patient_id);
    expect($referral->prenatal_visit_id)->toBe($visit->id);
    expect($referral->status)->toBe('Pending');
    expect($referral->created_by)->toBe($user->id);
});

it('builds a server-side snapshot with HIGH risk and preserved panel data', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $referral = Referral::latest('id')->first();
    expect($referral->assessment_snapshot)->not->toBeNull();
    expect($referral->assessment_snapshot['risk_level'])->toBe('HIGH');
    expect($referral->assessment_snapshot['urgency'])->toBe('URGENT_CLINICAL_REVIEW');
    expect($referral->assessment_snapshot['bp_assessment']['reason_code'])->toBe('BP-URG');
    expect($referral->assessment_snapshot['prenatal_visit_id'])->toBe($visit->id);
});

it('preserves factor and interaction evidence in the snapshot', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $referral = Referral::latest('id')->first();
    expect($referral->assessment_snapshot['factor_evidence'])->toHaveCount(1);
    expect($referral->assessment_snapshot['factor_evidence'][0]['code'])->toBe('CS-01');
    expect($referral->assessment_snapshot['interaction_evidence'])->toHaveCount(1);
    expect($referral->assessment_snapshot['interaction_evidence'][0]['code'])->toBe('INT-CS-PRES');
});

it('rejects a store attempt when the visit belongs to a different patient', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $other = linkedPatient('Otria');

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $other->id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('prenatal_visit_id');
    expect(Referral::count())->toBe(0);
});

it('rejects a store attempt for a soft-deleted visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $visit->delete();

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('prenatal_visit_id');
    expect(Referral::count())->toBe(0);
});

it('rejects a stale form when the visit is no longer HIGH at store time', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    // Page is loaded, then the visit outcome changes before submit.
    $visit->update(['risk_level' => 'LOW', 'urgency' => null]);

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('prenatal_visit_id');
    expect(Referral::count())->toBe(0);
});

it('never persists assessment_snapshot values sent by the client', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'assessment_snapshot' => ['risk_level' => 'LOW'],
        'created_by' => $user->id,
        'status' => 'Completed',
    ]));

    $response->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->assessment_snapshot['risk_level'])->toBe('HIGH');
    expect($referral->status)->toBe('Pending');
    expect($referral->created_by)->toBe($user->id);
});

it('blocks a second Pending referral for the same visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('prenatal_visit_id');
    expect(Referral::count())->toBe(1);
});

it('allows re-referral once the pending referral is closed', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $first = Referral::create([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'First referral',
        'referral_date' => now()->toDateString(),
        'status' => 'Completed',
        'completed_at' => now(),
    ]);
    expect($first->status)->toBe('Completed');

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
        'reason' => 'Re-refer on closed prior',
    ]));

    $response->assertRedirect(route('referrals.index'));
    expect(Referral::count())->toBe(2);
});

it('keeps the legacy manual referral flow working without a visit id', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = linkedPatient('Manual');

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $patient->id,
    ]));

    $response->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->prenatal_visit_id)->toBeNull();
    expect($referral->assessment_snapshot)->toBeNull();
    expect($referral->status)->toBe('Pending');
});

it('rejects a manual store for a delivered patient', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = linkedPatient('Delivered');
    $patient->update(['status' => 'DELIVERED']);

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $patient->id,
    ]));

    $response->assertSessionHasErrors('patient_id');
    expect(Referral::count())->toBe(0);
});

it('rejects a linked store for a delivered patient', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();
    $visit->patient->update(['status' => 'DELIVERED']);

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('patient_id');
    expect(Referral::count())->toBe(0);
});

it('keeps the stored snapshot immutable after later visit changes', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $referral = Referral::latest('id')->first();
    $before = $referral->assessment_snapshot;

    $visit->update(['risk_level' => 'LOW', 'assessment' => 'Changed later']);

    $referral->refresh();
    expect($referral->assessment_snapshot)->toBe($before);
    expect($referral->assessment_snapshot['risk_level'])->toBe('HIGH');
    expect($referral->assessment_snapshot['assessment'])->toBe('Urgent blood pressure concern');
});

it('audit logs a CREATE with an explicit linked description', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $referral = Referral::latest('id')->first();

    $log = \App\Models\AuditLog::where('module', 'REFERRAL')->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->action)->toBe('CREATE');
    expect($log->description)->toContain('PrenatalVisit #' . $referral->prenatal_visit_id);
});

it('shows the linked referral button only for HIGH assessments on the patient profile', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $high = linkedVisit();

    $response = $this->actingAs($user)->get(route('patients.show', $high->patient_id));
    $response->assertOk();
    $response->assertSee('Create Referral from this Assessment');

    $lowPatient = linkedPatient('LowP');
    PrenatalVisit::create([
        'patient_id' => $lowPatient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
    ]);

    $lowResponse = $this->actingAs($user)->get(route('patients.show', $lowPatient->id));
    $lowResponse->assertOk();
    $lowResponse->assertDontSee('Create Referral from this Assessment');
});

it('allows linked create for a modern HIGH assessment with structured metadata', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertOk()->assertSee('Linked Assessment Evidence (read-only)');
});

it('allows linked store for a modern HIGH assessment with structured metadata', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit();

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->prenatal_visit_id)->toBe($visit->id);
    expect($referral->assessment_snapshot)->not->toBeNull();
});

it('rejects linked create for a legacy HIGH visit without structured metadata', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit(['assessment_metadata' => null]);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertStatus(422);
});

it('rejects linked store for a legacy HIGH visit without structured metadata and creates no referral', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit(['assessment_metadata' => null]);

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]));

    $response->assertSessionHasErrors('prenatal_visit_id');
    expect(Referral::count())->toBe(0);
});

it('hides the linked referral button for a legacy HIGH visit without structured metadata', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit(['assessment_metadata' => null]);

    $response = $this->actingAs($user)->get(route('patients.show', $visit->patient_id));
    $response->assertOk();
    $response->assertDontSee('Create Referral from this Assessment');
});

it('still allows a manual referral for a legacy HIGH visit without structured metadata', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = linkedPatient('Legacy');

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
        'assessment_metadata' => null,
    ]);

    $response = $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $patient->id,
    ]));

    $response->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->patient_id)->toBe($patient->id);
    expect($referral->prenatal_visit_id)->toBeNull();
    expect($referral->assessment_snapshot)->toBeNull();
});

it('allows a linked referral for a modern HIGH visit with structured metadata and zero interactions', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $visit = linkedVisit([
        'assessment_metadata' => [
            'context' => ['assessment_date' => now()->toDateString()],
            'interaction_evidence' => [],
            'versions' => ['assessment_engine' => '1.0.0', 'clinical_rules' => '1.1.0', 'context' => 1],
            'assessed_at' => '2026-08-10T10:00:00+00:00',
        ],
    ]);

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $visit->patient_id,
        'prenatal_visit_id' => $visit->id,
    ]))->assertRedirect(route('referrals.index'));

    $referral = Referral::latest('id')->first();
    expect($referral->prenatal_visit_id)->toBe($visit->id);
    expect($referral->assessment_snapshot)->not->toBeNull();
    expect($referral->assessment_snapshot['interaction_evidence'])->toBe([]);
    expect($referral->assessment_snapshot['risk_level'])->toBe('HIGH');
});

it('keeps LOW and ASSESSMENT INCOMPLETE linked behavior unchanged', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $lowVisit = linkedVisit(['risk_level' => 'LOW', 'urgency' => null]);
    $incompleteVisit = linkedVisit(['risk_level' => 'ASSESSMENT INCOMPLETE']);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $lowVisit->patient_id,
        'prenatal_visit_id' => $lowVisit->id,
    ]))->assertStatus(403);

    $this->actingAs($user)->get(route('referrals.create', [
        'id' => $incompleteVisit->patient_id,
        'prenatal_visit_id' => $incompleteVisit->id,
    ]))->assertStatus(403);

    $this->actingAs($user)->post(route('referrals.store'), referralPayload([
        'patient_id' => $lowVisit->patient_id,
        'prenatal_visit_id' => $lowVisit->id,
    ]))->assertSessionHasErrors('prenatal_visit_id');
});