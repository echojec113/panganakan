<?php

namespace Tests\Feature;

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\PregnancyOutcome;
use App\Models\Referral;
use App\Models\Ultrasound;
use App\Models\User;
use App\Notifications\EddApproachingNotification;
use App\Notifications\PendingRepeatBloodPressureNotification;
use App\Notifications\PastEddNeedsReviewNotification;
use App\Notifications\PatientBecameHighRiskNotification;
use App\Notifications\ReferralClosedNotification;
use App\Notifications\ReferralCreatedNotification;
use App\Notifications\UrgentBloodPressureNotification;
use App\Services\PatientAssessmentRecalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * DEPLA notification enhancements: clinic-wide recipients, the HIGH-risk
 * transition alert and the two scheduled EDD alerts.
 *
 * Recipient model under test: every notification goes to ALL active
 * admin + staff accounts (single-clinic system) and never to archived
 * (soft-deleted) users.
 *
 * Time-based alerts are exercised through the real scheduled command, not by
 * faking the notification channel, so duplicate prevention is proven against
 * the persisted notifications ledger.
 */
class ClinicalAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $otherStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->otherStaff = User::factory()->create(['role' => 'staff']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function patient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'first_name' => 'Alert',
            'last_name' => 'Subject',
            'age' => 28,
            'address' => 'Test address',
            'contact_number' => '09171234567',
            'gravida' => 2,
            'para' => 1,
            'status' => 'ONGOING',
        ], $overrides));
    }

    private function visit(Patient $patient, array $overrides = []): PrenatalVisit
    {
        return PrenatalVisit::create(array_merge([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateString(),
            'bp_sys' => 120,
            'bp_dia' => 80,
            'weight' => 60,
            'gestational_age' => 20,
            'risk_level' => 'LOW',
        ], $overrides));
    }

    private function visitPayload(int $patientId, array $overrides = []): array
    {
        return array_merge([
            'patient_id' => $patientId,
            'visit_date' => now()->toDateString(),
            'bp_sys' => 120,
            'bp_dia' => 80,
            'weight' => 60,
            'gestational_age' => 20,
            'hypertension' => 0,
            'diabetes' => 0,
            'anemia' => 0,
        ], $overrides);
    }

    private function runClinicalAlerts(): void
    {
        $this->artisan('notifications:check-clinical-alerts')->assertSuccessful();
    }

    private function storedCount(string $notificationType): int
    {
        return DB::table('notifications')->where('type', $notificationType)->count();
    }

    // ------------------------------------------------------------------
    // PART 1 / 8 / 9 - Recipients
    // ------------------------------------------------------------------

    public function test_urgent_bp_alert_reaches_every_active_clinic_user(): void
    {
        Notification::fake();

        $patient = $this->patient(['assigned_staff_id' => $this->staff->id]);

        $this->actingAs($this->staff)->post(route('prenatal-visits.store'), $this->visitPayload($patient->id, [
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]));

        Notification::assertSentTo($this->admin, UrgentBloodPressureNotification::class);
        Notification::assertSentTo($this->staff, UrgentBloodPressureNotification::class);
        Notification::assertSentTo($this->otherStaff, UrgentBloodPressureNotification::class);

        Notification::assertSentTo($this->admin, PendingRepeatBloodPressureNotification::class);
        Notification::assertSentTo($this->otherStaff, PendingRepeatBloodPressureNotification::class);
    }

    public function test_archived_clinic_users_do_not_receive_new_alerts(): void
    {
        $archivedAdmin = User::factory()->create(['role' => 'admin']);
        $archivedStaff = User::factory()->create(['role' => 'staff']);
        $archivedAdmin->delete();
        $archivedStaff->delete();

        Notification::fake();

        $patient = $this->patient();

        $this->actingAs($this->staff)->post(route('prenatal-visits.store'), $this->visitPayload($patient->id, [
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]));

        Notification::assertSentTo($this->admin, UrgentBloodPressureNotification::class);
        Notification::assertNotSentTo($archivedAdmin, UrgentBloodPressureNotification::class);
        Notification::assertNotSentTo($archivedStaff, UrgentBloodPressureNotification::class);
        Notification::assertNotSentTo($archivedAdmin, PendingRepeatBloodPressureNotification::class);
        Notification::assertNotSentTo($archivedStaff, PendingRepeatBloodPressureNotification::class);
    }

    public function test_referral_creation_reaches_active_admins_and_staff(): void
    {
        Notification::fake();

        $patient = $this->patient();

        $this->actingAs($this->staff)->post(route('referrals.store'), [
            'patient_id' => $patient->id,
            'referred_to' => 'City General Hospital',
            'reason' => 'Ongoing monitoring required',
            'date_referred' => now()->toDateString(),
        ]);

        Notification::assertSentTo($this->admin, ReferralCreatedNotification::class);
        Notification::assertSentTo($this->staff, ReferralCreatedNotification::class);
        Notification::assertSentTo($this->otherStaff, ReferralCreatedNotification::class);
    }

    public function test_referral_status_update_reaches_active_admins_and_staff(): void
    {
        Notification::fake();

        $patient = $this->patient();

        $referral = Referral::create([
            'patient_id' => $patient->id,
            'created_by' => $this->staff->id,
            'referred_to' => 'City General Hospital',
            'reason' => 'Ongoing monitoring required',
            'referral_date' => now()->toDateString(),
            'status' => 'Pending',
        ]);

        $this->actingAs($this->staff)->post(route('referrals.complete', $referral->id));

        Notification::assertSentTo($this->admin, ReferralClosedNotification::class);
        Notification::assertSentTo($this->staff, ReferralClosedNotification::class);
        Notification::assertSentTo($this->otherStaff, ReferralClosedNotification::class);
    }

    // ------------------------------------------------------------------
    // PART 2 - Patient Became High Risk
    // ------------------------------------------------------------------

    public function test_low_to_high_effective_risk_notifies_the_clinic_once(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $this->visit($patient, [
            'visit_date' => now()->subDays(10)->toDateString(),
            'risk_level' => 'LOW',
        ]);

        $this->actingAs($this->staff)->post(route('prenatal-visits.store'), $this->visitPayload($patient->id, [
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]));

        Notification::assertSentToTimes($this->admin, PatientBecameHighRiskNotification::class, 1);
        Notification::assertSentToTimes($this->staff, PatientBecameHighRiskNotification::class, 1);
        Notification::assertSentToTimes($this->otherStaff, PatientBecameHighRiskNotification::class, 1);
    }

    public function test_high_to_high_does_not_send_another_notification(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $this->visit($patient, ['risk_level' => 'HIGH']);

        $this->actingAs($this->staff)->post(route('prenatal-visits.store'), $this->visitPayload($patient->id, [
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]));

        Notification::assertNotSentTo($this->admin, PatientBecameHighRiskNotification::class);
        Notification::assertNotSentTo($this->staff, PatientBecameHighRiskNotification::class);
    }

    public function test_updating_an_already_high_visit_does_not_resend(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $visit = $this->visit($patient, ['risk_level' => 'HIGH']);

        $this->actingAs($this->staff)->put(route('prenatal-visits.update', $visit->id), $this->visitPayload($patient->id, [
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]));

        Notification::assertNotSentTo($this->admin, PatientBecameHighRiskNotification::class);
        Notification::assertNotSentTo($this->staff, PatientBecameHighRiskNotification::class);
    }

    public function test_unrelated_non_high_update_does_not_notify(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $visit = $this->visit($patient, ['risk_level' => 'LOW']);

        $this->actingAs($this->staff)->put(route('prenatal-visits.update', $visit->id), $this->visitPayload($patient->id, [
            'weight' => 61,
        ]));

        Notification::assertNotSentTo($this->admin, PatientBecameHighRiskNotification::class);
        Notification::assertNotSentTo($this->staff, PatientBecameHighRiskNotification::class);
    }

    public function test_record_driven_recalculation_notifies_once_and_is_idempotent(): void
    {
        Notification::fake();

        $patient = $this->patient();
        $this->visit($patient, [
            'risk_level' => 'ASSESSMENT INCOMPLETE',
            'bp_sys' => 165,
            'bp_dia' => 110,
        ]);

        // All three clinical records now exist, so the recalculation service
        // re-runs the assessment for the incomplete visit.
        MedicalHistory::create(['patient_id' => $patient->id]);
        Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);
        BirthPlan::create(['patient_id' => $patient->id]);

        $service = app(PatientAssessmentRecalculationService::class);

        $service->recalculateIncompleteVisits($patient->id);

        Notification::assertSentToTimes($this->admin, PatientBecameHighRiskNotification::class, 1);
        Notification::assertSentToTimes($this->otherStaff, PatientBecameHighRiskNotification::class, 1);

        // Running the same recalculation again must not re-alert: the visit is
        // finalized HIGH now (HIGH -> HIGH) and no incomplete visit remains.
        $service->recalculateIncompleteVisits($patient->id);

        Notification::assertSentToTimes($this->admin, PatientBecameHighRiskNotification::class, 1);
        Notification::assertSentToTimes($this->otherStaff, PatientBecameHighRiskNotification::class, 1);
    }

    // ------------------------------------------------------------------
    // PART 4 - EDD Approaching (scheduled command)
    // ------------------------------------------------------------------

    public function test_edd_approaching_alert_reaches_active_admins_and_staff(): void
    {
        $patient = $this->patient(['edd' => now()->addDays(3)->toDateString()]);

        $this->runClinicalAlerts();

        $this->assertSame(3, $this->storedCount(EddApproachingNotification::class));
        $this->assertDatabaseHas('notifications', [
            'type' => EddApproachingNotification::class,
            'notifiable_id' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => EddApproachingNotification::class,
            'notifiable_id' => $this->otherStaff->id,
        ]);
        $this->assertSeeDestination($patient, EddApproachingNotification::class);
    }

    public function test_edd_approaching_does_not_fire_outside_the_seven_day_window(): void
    {
        $this->patient(['edd' => now()->addDays(30)->toDateString()]);
        $this->patient(['edd' => now()->addDays(8)->toDateString()]);

        $this->runClinicalAlerts();

        $this->assertSame(0, $this->storedCount(EddApproachingNotification::class));
    }

    public function test_edd_approaching_does_not_fire_for_a_completed_pregnancy(): void
    {
        $this->patient([
            'edd' => now()->addDays(3)->toDateString(),
            'status' => 'DELIVERED',
        ]);

        $this->runClinicalAlerts();

        $this->assertSame(0, $this->storedCount(EddApproachingNotification::class));
    }

    public function test_edd_approaching_is_idempotent_across_scheduler_runs(): void
    {
        $this->patient(['edd' => now()->addDays(2)->toDateString()]);

        $this->runClinicalAlerts();
        $this->runClinicalAlerts();

        $this->assertSame(3, $this->storedCount(EddApproachingNotification::class));
    }

    public function test_edd_approaching_skips_archived_users(): void
    {
        $archived = User::factory()->create(['role' => 'staff']);
        $archived->delete();

        $this->patient(['edd' => now()->addDays(1)->toDateString()]);

        $this->runClinicalAlerts();

        $this->assertSame(3, $this->storedCount(EddApproachingNotification::class));
        $this->assertDatabaseMissing('notifications', [
            'type' => EddApproachingNotification::class,
            'notifiable_id' => $archived->id,
        ]);
    }

    // ------------------------------------------------------------------
    // PART 5 - Past EDD / Needs Review (scheduled command)
    // ------------------------------------------------------------------

    public function test_past_edd_alert_reaches_active_admins_and_staff(): void
    {
        $patient = $this->patient(['edd' => now()->subDays(3)->toDateString()]);

        $this->runClinicalAlerts();

        $this->assertSame(3, $this->storedCount(PastEddNeedsReviewNotification::class));
        $this->assertDatabaseHas('notifications', [
            'type' => PastEddNeedsReviewNotification::class,
            'notifiable_id' => $this->admin->id,
        ]);
        $this->assertSeeDestination($patient, PastEddNeedsReviewNotification::class);
    }

    public function test_past_edd_does_not_fire_on_or_before_the_edd_itself(): void
    {
        $this->patient(['edd' => now()->toDateString()]);

        $this->runClinicalAlerts();

        $this->assertSame(0, $this->storedCount(PastEddNeedsReviewNotification::class));
    }

    public function test_past_edd_does_not_fire_for_a_delivered_pregnancy(): void
    {
        $this->patient([
            'edd' => now()->subDays(10)->toDateString(),
            'status' => 'DELIVERED',
        ]);

        $this->runClinicalAlerts();

        $this->assertSame(0, $this->storedCount(PastEddNeedsReviewNotification::class));
    }

    public function test_past_edd_does_not_fire_when_a_completion_outcome_is_recorded(): void
    {
        $patient = $this->patient(['edd' => now()->subDays(10)->toDateString()]);

        PregnancyOutcome::create([
            'patient_id' => $patient->id,
            'outcome_type' => 'DELIVERED',
            'confirmation_source' => 'CLINIC_RECORD',
            'confirmed_at' => now(),
            'confirmed_by' => $this->admin->id,
        ]);

        $this->runClinicalAlerts();

        $this->assertSame(0, $this->storedCount(PastEddNeedsReviewNotification::class));
    }

    public function test_past_edd_alert_never_changes_patient_state(): void
    {
        $lmp = now()->subDays(283)->toDateString();
        $edd = now()->subDays(3)->toDateString();

        $patient = $this->patient(['lmp' => $lmp, 'edd' => $edd]);

        $this->runClinicalAlerts();

        $patient->refresh();

        $this->assertSame('ONGOING', $patient->status);
        $this->assertSame($edd, $patient->edd->toDateString());
        $this->assertSame($lmp, $patient->lmp->toDateString());
        $this->assertDatabaseCount('pregnancy_outcomes', 0);
        $this->assertDatabaseCount('babies', 0);
    }

    public function test_past_edd_is_idempotent_across_scheduler_runs(): void
    {
        $this->patient(['edd' => now()->subDays(5)->toDateString()]);

        $this->runClinicalAlerts();
        $this->runClinicalAlerts();

        $this->assertSame(3, $this->storedCount(PastEddNeedsReviewNotification::class));
    }

    public function test_alerts_are_user_scoped_and_render_in_the_shared_tray(): void
    {
        $this->patient(['edd' => now()->subDays(2)->toDateString()]);

        $this->runClinicalAlerts();

        $response = $this->actingAs($this->otherStaff)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Past Expected Delivery Date', false);
        $response->assertSee('View patient', false);

        // Another user's tray is unaffected (self-scoped rows only).
        $freshUser = User::factory()->create(['role' => 'staff']);
        $this->actingAs($freshUser)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Past Expected Delivery Date', false);
    }

    // ------------------------------------------------------------------
    // Assertions
    // ------------------------------------------------------------------

    /** The notification payload must point at the same patient's profile. */
    private function assertSeeDestination(Patient $patient, string $notificationType): void
    {
        $row = DB::table('notifications')
            ->where('type', $notificationType)
            ->where('notifiable_id', $this->admin->id)
            ->first();

        $this->assertNotNull($row, 'Expected an admin row for ' . $notificationType);

        $data = json_decode((string) $row->data, true);

        $this->assertSame('patients.show', $data['destination']['route'] ?? null);
        $this->assertSame(['patient' => $patient->id], $data['destination']['parameters'] ?? null);
        $this->assertNotEmpty($data['event_key'] ?? null);
    }
}
