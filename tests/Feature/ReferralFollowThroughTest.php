<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralFollowThroughService;

function followThroughUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function followThroughPatient(string $firstName = 'Rita'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Bautista',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

function followThroughReferral(Patient $patient, User $user, array $overrides = []): Referral
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

function followThroughService(): ReferralFollowThroughService
{
    return app(ReferralFollowThroughService::class);
}

it('creates a referral with the patient left ONGOING (lifecycle decoupled)', function () {
    $user = followThroughUser();
    $patient = followThroughPatient();

    $this->actingAs($user)->post(route('referrals.store'), [
        'patient_id' => $patient->id,
        'referred_to' => 'Provincial Hospital',
        'doctor_name' => 'Dr. Cruz',
        'reason' => 'Needs specialist care',
        'notes' => 'Test',
        'date_referred' => now()->toDateString(),
    ])->assertRedirect(route('referrals.index'));

    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');

    $referral = Referral::latest('id')->first();
    expect($referral->status)->toBe('Pending');
    expect($referral->completed_at)->toBeNull();
    expect($referral->refusal_recorded_at)->toBeNull();
});

it('completes a pending referral with a completed_at timestamp', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    followThroughService()->complete($referral, $user);

    $referral->refresh();
    expect($referral->status)->toBe('Completed');
    expect($referral->completed_at)->not->toBeNull();
    expect($referral->refusal_notes)->toBeNull();
    expect($referral->refusal_recorded_at)->toBeNull();
    expect($referral->waiver_signed)->toBeFalse();
});

it('does not complete a referral that is already closed', function () {
    $user = followThroughUser();

    $completed = followThroughReferral(followThroughPatient('Mila'), $user, ['status' => 'Completed']);
    $refused = followThroughReferral(followThroughPatient('Nina'), $user, ['status' => 'Refused']);
    $cancelled = followThroughReferral(followThroughPatient('Opa'), $user, ['status' => 'Cancelled']);

    foreach ([$completed, $refused, $cancelled] as $referral) {
        expect(fn () => followThroughService()->complete($referral, $user))
            ->toThrow(DomainException::class);
        $referral->refresh();
        expect($referral->status)->not->toBe('Pending');
    }
});

it('refuses a pending referral with server-stamped metadata', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    followThroughService()->refuse($referral, $user, 'Patient declined the specialist referral after counseling.', true);

    $referral->refresh();
    expect($referral->status)->toBe('Refused');
    expect($referral->completed_at)->toBeNull();
    expect($referral->refusal_notes)->toBe('Patient declined the specialist referral after counseling.');
    expect($referral->refusal_recorded_at)->not->toBeNull();
    expect($referral->refusal_recorded_by)->toBe($user->id);
    expect($referral->waiver_signed)->toBeTrue();
});

it('requires a refusal note', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    expect(fn () => followThroughService()->refuse($referral, $user, '   ', false))
        ->toThrow(DomainException::class);
});

it('does not refuse a referral that is already closed', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user, ['status' => 'Completed']);

    expect(fn () => followThroughService()->refuse($referral, $user, 'Patient declined.', false))
        ->toThrow(DomainException::class);

    $referral->refresh();
    expect($referral->status)->toBe('Completed');
});

it('cancels a pending referral', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    followThroughService()->cancel($referral, $user);

    $referral->refresh();
    expect($referral->status)->toBe('Cancelled');
    expect($referral->completed_at)->toBeNull();
    expect($referral->refusal_recorded_at)->toBeNull();
});

it('preserves original referral notes when a referral is cancelled', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user, [
        'notes' => 'Original creation note that must survive.',
    ]);

    followThroughService()->cancel($referral, $user);

    $referral->refresh();
    expect($referral->status)->toBe('Cancelled');
    expect($referral->notes)->toBe('Original creation note that must survive.');
});

it('does not cancel an already-refused referral', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user, ['status' => 'Refused']);

    expect(fn () => followThroughService()->cancel($referral, $user))
        ->toThrow(DomainException::class);

    $referral->refresh();
    expect($referral->status)->toBe('Refused');
});

it('does not reopen a closed referral from a new referral of same patient', function () {
    $user = followThroughUser();
    $patient = followThroughPatient();

    $first = followThroughReferral($patient, $user);
    followThroughService()->complete($first, $user);

    $second = followThroughReferral($patient, $user);

    expect($first->status)->toBe('Completed');
    expect($second->status)->toBe('Pending');
    expect(Referral::where('patient_id', $patient->id)->count())->toBe(2);
});

it('blocks all mutation routes for referrals of delivered patients', function () {
    $user = followThroughUser();
    $patient = followThroughPatient('Dette');
    $patient->update(['status' => 'DELIVERED']);
    $referral = followThroughReferral($patient, $user);

    $this->actingAs($user)->post(route('referrals.complete', $referral->id))
        ->assertRedirect()->assertSessionHas('error');
    $this->actingAs($user)->post(route('referrals.cancel', $referral->id))
        ->assertRedirect()->assertSessionHas('error');
    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), [
        'refusal_notes' => 'Patient declined the specialist referral.',
        'waiver_signed' => 1,
    ])->assertRedirect()->assertSessionHas('error');

    $referral->refresh();
    expect($referral->status)->toBe('Pending');
    expect($referral->completed_at)->toBeNull();
    expect($referral->refusal_recorded_at)->toBeNull();
});

it('validates required refusal notes on the route', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), [
        'refusal_notes' => '',
        'waiver_signed' => 0,
    ])->assertSessionHasErrors('refusal_notes');

    $referral->refresh();
    expect($referral->status)->toBe('Pending');
});

it('never accepts browser-supplied refusal timestamps or actor ids', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user);

    $this->actingAs($user)->post(route('referrals.refuse', $referral->id), [
        'refusal_notes' => 'The patient requested to reconsider next month.',
        'waiver_signed' => 0,
        'refusal_recorded_at' => '2001-01-01 00:00:00',
        'refusal_recorded_by' => 99999,
        'status' => 'Completed',
    ])->assertRedirect();

    $referral->refresh();
    expect($referral->status)->toBe('Refused');
    expect($referral->refusal_recorded_by)->toBe($user->id);
    expect($referral->refusal_recorded_at->year)->toBe((int) now()->year);
});

it('audit logs completed/refused/cancelled transitions', function () {
    $user = followThroughUser();
    $completeRef = followThroughReferral(followThroughPatient('Pol'), $user);
    $refuseRef = followThroughReferral(followThroughPatient('Rhay'), $user);
    $cancelRef = followThroughReferral(followThroughPatient('San'), $user);

    $this->actingAs($user)->post(route('referrals.complete', $completeRef->id));
    $this->actingAs($user)->post(route('referrals.refuse', $refuseRef->id), [
        'refusal_notes' => 'Patient declined due to transport difficulty.',
        'waiver_signed' => 1,
    ]);
    $this->actingAs($user)->post(route('referrals.cancel', $cancelRef->id));

    $logs = AuditLog::where('module', 'REFERRAL')->where('action', 'UPDATE')->latest('id')->get()->take(3);

    $descriptions = $logs->pluck('description')->implode(' | ');

    expect($descriptions)->toContain('Completed referral #' . $completeRef->id);
    expect($descriptions)->toContain('Recorded refusal for referral #' . $refuseRef->id);
    expect($descriptions)->toContain('Cancelled referral #' . $cancelRef->id);
});

it('does not log a mutation when a route transition is blocked', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user, ['status' => 'Completed']);

    $before = AuditLog::where('module', 'REFERRAL')->count();

    $this->actingAs($user)->post(route('referrals.complete', $referral->id))
        ->assertSessionHas('error');

    expect(AuditLog::where('module', 'REFERRAL')->count())->toBe($before);
});

it('shows a Pending Referral indicator without replacing the next-visit label', function () {
    $user = followThroughUser();
    $patient = followThroughPatient('Victa');
    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Hypertension'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->addDays(6)->toDateString(),
    ]);

    $referral = followThroughReferral($patient, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertSeeText('Pending Referral')
        ->assertDontSee('Overdue');

    followThroughService()->complete($referral, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertDontSeeText('Pending Referral');
});

it('keeps the monitoring label on a legacy REFERRED patient with referral activity', function () {
    $user = followThroughUser();
    $patient = Patient::create([
        'first_name' => 'Legacy',
        'last_name' => 'Referred',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'legacy@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'REFERRED',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Hypertension'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertSeeText('Legacy Referred')
        ->assertDontSee('Overdue');
});

it('keeps normal prenatal overdue behavior despite a pending referral', function () {
    $user = followThroughUser();
    $patient = followThroughPatient('Wendy');
    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Preeclampsia'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->subDays(3)->toDateString(),
    ]);

    $referral = followThroughReferral($patient, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertSeeText('Pending Referral')
        ->assertSeeText('Overdue');

    followThroughService()->refuse($referral, $user, 'Patient refused this referral during the checkup.', true);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertDontSeeText('Pending Referral')
        ->assertSeeText('Overdue');
});

it('does not restore overdue logic after closing an unrelated referral', function () {
    $user = followThroughUser();
    $patient = followThroughPatient('Yvette');
    $futureVisit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subWeek()->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['Hypertension'],
        'assessment' => 'High risk',
        'next_visit_date' => now()->addDays(6)->toDateString(),
    ]);

    $referral = followThroughReferral($patient, $user);
    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertSeeText('Pending Referral')
        ->assertDontSee('Overdue');

    followThroughService()->complete($referral, $user);

    $this->actingAs($user)->get(route('risk.monitoring'))
        ->assertDontSeeText('Pending Referral')
        ->assertDontSee('Overdue');
    expect($futureVisit->fresh()->getMonitoringNextVisitLabel())
        ->toBe(\Carbon\Carbon::parse($futureVisit->next_visit_date)->format('M d, Y'));
});

it('does not overwrite original referral notes via the cancel route', function () {
    $user = followThroughUser();
    $referral = followThroughReferral(followThroughPatient(), $user, [
        'notes' => 'Original creation note that must survive.',
    ]);

    $this->actingAs($user)->post(route('referrals.cancel', $referral->id), [
        'notes' => 'Some bogus cancellation narrative posted by a client.',
    ])->assertRedirect();

    $referral->refresh();
    expect($referral->status)->toBe('Cancelled');
    expect($referral->notes)->toBe('Original creation note that must survive.');
});

it('renders the corrected refusal wording without a false close claim', function () {
    $user = followThroughUser();
    $patient = followThroughPatient('Dara');
    $referral = followThroughReferral($patient, $user);
    followThroughService()->refuse($referral, $user, 'Patient refused this referral during the checkup.', true);

    $this->actingAs($user)->get(route('referrals.show', $referral->id))
        ->assertOk()
        ->assertSee('because the referral was refused rather than completed')
        ->assertDontSee('did not close');
});