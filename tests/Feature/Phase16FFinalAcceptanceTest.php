<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralFollowThroughService;

/**
 * Phase 16F — final Sprint 16 acceptance gate.
 *
 * These tests close the genuine coverage gaps in the existing Sprint 16
 * suites (AssessmentLinkedReferralTest, ReferralFollowThroughTest,
 * Phase16EUiTest, RiskMonitoringStatusTest, ReferralAnalyticsTest) without
 * duplicating their assertions:
 *   - G: referral transitions never end the pregnancy (completion, refusal,
 *     cancellation all keep patient.status = ONGOING).
 *   - G9/L4: legacy patient.status = REFERRED is never rewritten and no
 *     backfill occurs when a new referral is created or closed.
 *   - J1: mutation routes remain staff-only while read routes stay open.
 *   - K: index status filter and pagination behave.
 *   - D6/E7: Completed and Refused detail pages render NO mutation controls.
 */

function finalAcceptanceUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function finalAcceptancePatient(string $firstName, string $status = 'ONGOING'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'FinalGate',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => $status,
    ]);
}

function finalAcceptanceReferral(Patient $patient, User $user, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ], $overrides));
}

function finalAcceptanceService(): ReferralFollowThroughService
{
    return app(ReferralFollowThroughService::class);
}

it('G: completing/refusing/cancelling a referral never ends the pregnancy', function () {
    $user = finalAcceptanceUser();

    $completeP = finalAcceptancePatient('Completa');
    $refuseP = finalAcceptancePatient('Refusala');
    $cancelP = finalAcceptancePatient('Cancella');

    $complete = finalAcceptanceReferral($completeP, $user);
    $refuse = finalAcceptanceReferral($refuseP, $user);
    $cancel = finalAcceptanceReferral($cancelP, $user);

    // Drive the full HTTP routes rather than the service directly.
    $this->actingAs($user)->post(route('referrals.complete', $complete->id))->assertRedirect();
    $this->actingAs($user)->post(route('referrals.refuse', $refuse->id), [
        'refusal_notes' => 'Patient declined the specialist referral after counseling.',
        'waiver_signed' => 1,
    ])->assertRedirect();
    $this->actingAs($user)->post(route('referrals.cancel', $cancel->id))->assertRedirect();

    expect($complete->fresh()->status)->toBe('Completed');
    expect($refuse->fresh()->status)->toBe('Refused');
    expect($cancel->fresh()->status)->toBe('Cancelled');

    // Pregnancy lifecycle stays decoupled in every outcome.
    expect($completeP->fresh()->status)->toBe('ONGOING');
    expect($refuseP->fresh()->status)->toBe('ONGOING');
    expect($cancelP->fresh()->status)->toBe('ONGOING');
});

it('keeps legacy REFERRED patients intact: no rewrite and no backfill on new activity', function () {
    $user = finalAcceptanceUser();
    $legacy = finalAcceptancePatient('Heritage', 'REFERRED');

    // A historical REFERRED row is read untouched, with a new referral created
    // and closed against it — none of this may rewrite the legacy status.
    $new = finalAcceptanceReferral($legacy, $user);
    $this->actingAs($user)->post(route('referrals.complete', $new->id))->assertRedirect();

    $legacy->refresh();
    expect($legacy->status)->toBe('REFERRED');
    expect(Referral::where('patient_id', $legacy->id)->count())->toBe(1);
    expect($new->fresh()->status)->toBe('Completed');
});

it('J1: referral mutation routes stay staff-only while read routes stay open', function () {
    $admin = finalAcceptanceUser('admin');
    $user = finalAcceptanceUser();
    $referral = finalAcceptanceReferral(finalAcceptancePatient('Adm'), $user);

    // Admin gets read access.
    $this->actingAs($admin)->get(route('referrals.index'))->assertOk();
    $this->actingAs($admin)->get(route('referrals.show', $referral->id))->assertOk();
    $this->actingAs($admin)->get(route('referrals.print', $referral->id))->assertOk();

    // Admin (non-staff) is denied every mutation route.
    $this->actingAs($admin)->post(route('referrals.store'), [
        'patient_id' => $referral->patient_id,
        'referred_to' => 'Hospital',
        'reason' => 'Test',
        'date_referred' => now()->toDateString(),
    ])->assertForbidden();
    $this->actingAs($admin)->post(route('referrals.complete', $referral->id))->assertForbidden();
    $this->actingAs($admin)->post(route('referrals.refuse', $referral->id), [
        'refusal_notes' => 'Patient declined during the checkup.',
        'waiver_signed' => 1,
    ])->assertForbidden();
    $this->actingAs($admin)->post(route('referrals.cancel', $referral->id))->assertForbidden();

    // No referral was mutated as a side effect of the denied attempts.
    expect($referral->fresh()->status)->toBe('Pending');
});

it('K: index status filter and search work at the HTTP layer', function () {
    $user = finalAcceptanceUser();
    $patientA = finalAcceptancePatient('Alfa');
    $patientB = finalAcceptancePatient('Beta');
    $patientC = finalAcceptancePatient('Gamma');

    finalAcceptanceReferral($patientA, $user, ['status' => 'Pending']);
    finalAcceptanceReferral($patientB, $user, ['status' => 'Completed', 'completed_at' => now()]);
    finalAcceptanceReferral($patientC, $user, ['status' => 'Refused', 'refusal_recorded_at' => now(), 'refusal_recorded_by' => $user->id, 'refusal_notes' => 'Declined.']);

    $pending = $this->actingAs($user)->get(route('referrals.index', ['status' => 'Pending']));
    $pending->assertOk()->assertSeeText('Alfa FinalGate')->assertDontSeeText('Beta FinalGate')->assertDontSeeText('Gamma FinalGate');

    $refused = $this->actingAs($user)->get(route('referrals.index', ['status' => 'Refused']));
    $refused->assertOk()->assertSeeText('Gamma FinalGate')->assertDontSeeText('Alfa FinalGate');

    $search = $this->actingAs($user)->get(route('referrals.index', ['search' => 'Beta']));
    $search->assertOk()->assertSeeText('Beta FinalGate')->assertDontSeeText('Alfa FinalGate');
});

it('K3: index pagination shows 15 rows then the remainder', function () {
    $user = finalAcceptanceUser();

    foreach (range(1, 16) as $i) {
        finalAcceptanceReferral(finalAcceptancePatient('P' . $i), $user);
    }

    $pageOne = $this->actingAs($user)->get(route('referrals.index'))->assertOk();
    $pageTwo = $this->actingAs($user)->get(route('referrals.index', ['page' => 2]))->assertOk();

    // Each row renders a fixed "View Referral" link twice (mobile card and
    // desktop table), giving an unambiguous per-row marker.
    expect(substr_count($pageOne->getContent(), 'View Referral'))->toBe(30);
    expect(substr_count($pageTwo->getContent(), 'View Referral'))->toBe(2);
});

it('D6/E7: completed and refused detail pages render no mutation controls', function () {
    $user = finalAcceptanceUser();
    $complete = finalAcceptanceReferral(finalAcceptancePatient('Done'), $user, ['status' => 'Completed', 'completed_at' => now()]);
    $refused = finalAcceptanceReferral(finalAcceptancePatient('Nope'), $user, [
        'status' => 'Refused',
        'refusal_recorded_at' => now(),
        'refusal_recorded_by' => $user->id,
        'refusal_notes' => 'Patient declined.',
        'waiver_signed' => false,
    ]);

    foreach ([$complete->id, $refused->id] as $id) {
        $content = $this->actingAs($user)->get(route('referrals.show', $id))->assertOk()->getContent();
        expect($content)->not->toContain('Mark Completed');
        expect($content)->not->toContain('Record Refusal');
        expect($content)->not->toContain('Cancel Referral');
    }
});