<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Referral lifecycle: Create Referral re-eligibility (only a Cancelled
 * latest non-archived referral re-opens the legacy REFERRED path),
 * one-row-per-patient Referrals index with expandable history, latest-status
 * filtering, archived exclusion, per-referral action IDs and the manual-mode
 * duplicate Pending guard.
 */
class ReferralLifecycleOneRowHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    private function patient(string $first, string $last = 'Lifecycle', string $status = 'ONGOING'): Patient
    {
        return Patient::create([
            'first_name' => $first,
            'last_name' => $last,
            'age' => 28,
            'address' => 'Test address',
            'contact_number' => '09171234567',
            'gravida' => 1,
            'para' => 0,
            'status' => $status,
        ]);
    }

    private function highAssessment(Patient $patient): PrenatalVisit
    {
        return PrenatalVisit::create([
            'patient_id' => $patient->id,
            'visit_date' => now()->toDateString(),
            'risk_level' => 'HIGH',
        ]);
    }

    private function referral(Patient $patient, array $overrides = []): Referral
    {
        return Referral::create(array_merge([
            'patient_id' => $patient->id,
            'created_by' => $this->staff->id,
            'referred_to' => 'City Hospital',
            'reason' => 'High-risk referral for evaluation',
            'referral_date' => now()->toDateString(),
            'status' => 'Pending',
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Create Referral search eligibility
    // ------------------------------------------------------------------

    public function test_ongoing_patient_with_cancelled_referral_is_searchable_again(): void
    {
        $patient = $this->patient('Cancella');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Cancella']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === [$patient->id])
            ->assertSee(route('referrals.create', $patient->id), false);
    }

    public function test_legacy_referred_patient_with_cancelled_latest_referral_is_searchable_again(): void
    {
        $patient = $this->patient('Heritage', 'CancelledLatest', 'REFERRED');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Heritage']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === [$patient->id])
            ->assertSee(route('referrals.create', $patient->id), false);
    }

    public function test_legacy_referred_patient_with_completed_latest_referral_is_not_searchable(): void
    {
        $patient = $this->patient('Completia', 'LegacyCompleted', 'REFERRED');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Completed', 'completed_at' => now()]);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Completia']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_legacy_referred_patient_with_refused_latest_referral_is_not_searchable(): void
    {
        $patient = $this->patient('Refusia', 'LegacyRefused', 'REFERRED');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Refused']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Refusia']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_legacy_referred_patient_with_pending_referral_is_not_searchable(): void
    {
        $active = $this->patient('ActiveLegacy', 'PendingReferral', 'REFERRED');
        $this->highAssessment($active);
        $this->referral($active, ['status' => 'Pending']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'ActiveLegacy']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_first_time_referral_is_searchable_for_ongoing_and_legacy_referred_status(): void
    {
        $ongoing = $this->patient('FirstTimer', 'NoReferralYet', 'ONGOING');
        $this->highAssessment($ongoing);

        $legacy = $this->patient('FirstLegacy', 'NoRecordYet', 'REFERRED');
        $this->highAssessment($legacy);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient'))
            ->assertOk()
            ->assertViewHas('patients', function ($patients) use ($ongoing, $legacy) {
                $ids = $patients->pluck('id')->all();

                return in_array($ongoing->id, $ids, true)
                    && in_array($legacy->id, $ids, true);
            });
    }

    public function test_delivered_patient_with_closed_referral_stays_excluded_from_selector(): void
    {
        $patient = $this->patient('Delia', 'DeliveredCase', 'DELIVERED');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Delia']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_ongoing_patient_with_pending_referral_is_not_searchable(): void
    {
        $patient = $this->patient('Activa');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Pending']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Activa']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_ongoing_patient_with_completed_latest_referral_is_not_searchable(): void
    {
        $patient = $this->patient('Completus', 'OngoingCompleted');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Completed', 'completed_at' => now()]);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Completus']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_ongoing_patient_with_refused_latest_referral_is_not_searchable(): void
    {
        $patient = $this->patient('Refusus', 'OngoingRefused');
        $this->highAssessment($patient);
        $this->referral($patient, ['status' => 'Refused']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient', ['search' => 'Refusus']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === []);
    }

    public function test_high_assessment_requirement_still_excludes_non_high_patients(): void
    {
        $noReferral = $this->patient('LowRisk', 'NoReferral');
        PrenatalVisit::create([
            'patient_id' => $noReferral->id,
            'visit_date' => now()->toDateString(),
            'risk_level' => 'MEDIUM',
        ]);

        $cancelled = $this->patient('LowRisk', 'AfterCancel');
        PrenatalVisit::create([
            'patient_id' => $cancelled->id,
            'visit_date' => now()->toDateString(),
            'risk_level' => 'MEDIUM',
        ]);
        $this->referral($cancelled, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)
            ->get(route('referrals.select-patient'))
            ->assertOk()
            ->assertViewHas('patients', function ($patients) use ($noReferral, $cancelled) {
                $ids = $patients->pluck('id')->all();

                return ! in_array($noReferral->id, $ids, true)
                    && ! in_array($cancelled->id, $ids, true);
            });
    }

    // ------------------------------------------------------------------
    // Manual-mode duplicate Pending guard
    // ------------------------------------------------------------------

    public function test_manual_store_rejects_a_second_pending_referral_for_the_same_patient(): void
    {
        $patient = $this->patient('Duplica');
        $first = $this->referral($patient, ['status' => 'Pending']);

        $this->actingAs($this->staff)->post(route('referrals.store'), [
            'patient_id' => $patient->id,
            'referred_to' => 'Provincial Hospital',
            'reason' => 'Second pending attempt must be rejected',
            'date_referred' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasErrors('patient_id');

        expect(Referral::where('patient_id', $patient->id)->count())->toBe(1);
        expect($first->fresh()->status)->toBe('Pending');
    }

    public function test_manual_store_creates_a_new_referral_after_the_previous_one_was_cancelled(): void
    {
        $patient = $this->patient('Reflow');
        $cancelled = $this->referral($patient, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)->post(route('referrals.store'), [
            'patient_id' => $patient->id,
            'referred_to' => 'Provincial Hospital',
            'reason' => 'Follow-up referral after cancellation',
            'date_referred' => now()->toDateString(),
        ])->assertRedirect(route('referrals.index'))
            ->assertSessionHas('success', 'Referral created successfully.');

        $rows = Referral::where('patient_id', $patient->id)->orderBy('id')->get();

        expect($rows)->toHaveCount(2)
            ->and($rows[0]->id)->toBe($cancelled->id)
            ->and($rows[0]->status)->toBe('Cancelled')
            ->and($rows[0]->referred_to)->toBe($cancelled->referred_to)
            ->and($rows[1]->id)->not->toBe($cancelled->id)
            ->and($rows[1]->status)->toBe('Pending')
            ->and($patient->fresh()->status)->toBe('ONGOING');
    }

    // ------------------------------------------------------------------
    // Backend enforcement (create page + direct store POST)
    // ------------------------------------------------------------------

    public function test_direct_store_post_is_rejected_for_pending_completed_and_refused_latest_referrals(): void
    {
        foreach (['Pending', 'Completed', 'Refused'] as $index => $status) {
            $patient = $this->patient('Direct' . $index, 'Post' . $status, 'ONGOING');
            $this->highAssessment($patient);
            $this->referral($patient, [
                'status' => $status,
                'completed_at' => $status === 'Completed' ? now() : null,
            ]);

            $before = Referral::where('patient_id', $patient->id)->count();

            $this->actingAs($this->staff)->post(route('referrals.store'), [
                'patient_id' => $patient->id,
                'referred_to' => 'Provincial Hospital',
                'reason' => 'Direct POST must be rejected server-side',
                'date_referred' => now()->toDateString(),
            ])->assertRedirect()->assertSessionHasErrors('patient_id');

            expect(Referral::where('patient_id', $patient->id)->count())->toBe($before)
                ->and($patient->latestReferral->status)->toBe($status);
        }
    }

    public function test_create_page_is_refused_for_non_cancelled_latest_referral(): void
    {
        $completed = $this->patient('FormBlocked', 'CompletedLatest');
        $this->highAssessment($completed);
        $completedReferral = $this->referral($completed, ['status' => 'Completed', 'completed_at' => now()]);

        $this->actingAs($this->staff)
            ->get(route('referrals.create', $completed->id))
            ->assertRedirect();

        $pending = $this->patient('FormBlocked', 'PendingLatest');
        $this->highAssessment($pending);
        $this->referral($pending, ['status' => 'Pending']);

        $this->actingAs($this->staff)
            ->get(route('referrals.create', $pending->id))
            ->assertRedirect();

        $cancelled = $this->patient('FormOpen', 'CancelledLatest');
        $this->highAssessment($cancelled);
        $this->referral($cancelled, ['status' => 'Cancelled']);

        $this->actingAs($this->staff)
            ->get(route('referrals.create', $cancelled->id))
            ->assertOk();

        expect($completedReferral->fresh()->status)->toBe('Completed');
    }

    // ------------------------------------------------------------------
    // One-row-per-patient index with expandable history
    // ------------------------------------------------------------------

    public function test_completed_refused_and_cancelled_history_records_remain_intact(): void
    {
        $patient = $this->patient('Historia', 'RetainedRecords');
        $completed = $this->referral($patient, [
            'referral_date' => now()->subDays(6)->toDateString(),
            'referred_to' => 'Completed Clinic',
            'reason' => 'Completed historical record',
            'status' => 'Completed',
            'completed_at' => now()->subDays(6),
        ]);
        $refused = $this->referral($patient, [
            'referral_date' => now()->subDays(4)->toDateString(),
            'referred_to' => 'Refused Clinic',
            'reason' => 'Refused historical record',
            'status' => 'Refused',
        ]);
        $cancelled = $this->referral($patient, [
            'referral_date' => now()->subDays(2)->toDateString(),
            'referred_to' => 'Cancelled Clinic',
            'reason' => 'Cancelled historical record',
            'status' => 'Cancelled',
        ]);

        $response = $this->actingAs($this->staff)->get(route('referrals.index'));

        $response->assertOk()
            ->assertViewHas('referrals', fn ($referrals) => $referrals->count() === 1)
            ->assertSeeText('Completed Clinic')
            ->assertSeeText('Refused Clinic')
            ->assertSeeText('Cancelled Clinic')
            ->assertSeeText('Completed historical record')
            ->assertSeeText('Refused historical record')
            ->assertSeeText('Cancelled historical record');

        // The eligibility change touches selection only: every historical
        // row is preserved with its original ID, status and timestamps.
        $rows = Referral::where('patient_id', $patient->id)->orderBy('id')->get();

        expect($rows)->toHaveCount(3)
            ->and($rows[0]->id)->toBe($completed->id)
            ->and($rows[0]->status)->toBe('Completed')
            ->and($rows[1]->id)->toBe($refused->id)
            ->and($rows[1]->status)->toBe('Refused')
            ->and($rows[2]->id)->toBe($cancelled->id)
            ->and($rows[2]->status)->toBe('Cancelled');
    }

    public function test_index_renders_one_row_per_patient_with_full_newest_first_history(): void
    {
        $patient = $this->patient('Maria', 'Multiple');
        $first = $this->referral($patient, [
            'referral_date' => now()->subDays(5)->toDateString(),
            'referred_to' => 'First Clinic',
            'reason' => 'First referral reason',
        ]);
        $second = $this->referral($patient, [
            'referral_date' => now()->subDays(2)->toDateString(),
            'referred_to' => 'Second Clinic',
            'reason' => 'Second referral reason',
            'status' => 'Completed',
            'completed_at' => now(),
        ]);
        $third = $this->referral($patient, [
            'referral_date' => now()->toDateString(),
            'referred_to' => 'Third Clinic',
            'reason' => 'Third referral reason',
            'status' => 'Cancelled',
        ]);

        $response = $this->actingAs($this->staff)->get(route('referrals.index'));
        $response->assertOk();

        // Exactly one list row for the patient despite three referrals.
        $response->assertViewHas('referrals', function ($referrals) use ($patient) {
            return $referrals->count() === 1 && $referrals->first()->id === $patient->id;
        });

        // Main row shows the latest referral (Third / Cancelled).
        $response->assertSeeText('Third Clinic');
        $response->assertSeeText('Cancelled');

        // History renders every non-archived referral with its status.
        $response->assertSeeText('First Clinic');
        $response->assertSeeText('Second Clinic');
        $response->assertSeeText('First referral reason');
        $response->assertSeeText('Completed');

        // Newest-first history ordering.
        $content = $response->getContent();
        expect(strpos($content, 'Second referral reason'))
            ->toBeLessThan(strpos($content, 'First referral reason'));

        // Expandable history markup exists for both layouts.
        $response->assertSee('id="referral-history-' . $patient->id . '"', false);
        $response->assertSee('id="referral-history-mobile-' . $patient->id . '"', false);

        // Exactly two "View Referral" links (mobile card + desktop row) for
        // the latest referral; history entries use their own View/Print.
        expect(substr_count($content, 'View Referral'))->toBe(2);
    }

    public function test_index_history_actions_target_specific_referral_ids(): void
    {
        $patient = $this->patient('Links', 'ExplicitIds');
        $first = $this->referral($patient, ['referral_date' => now()->subDays(1)->toDateString()]);
        $second = $this->referral($patient, ['referral_date' => now()->toDateString()]);

        $response = $this->actingAs($this->staff)->get(route('referrals.index'));
        $response->assertOk()
            ->assertSee(route('referrals.show', $first->id), false)
            ->assertSee(route('referrals.print', $first->id), false)
            ->assertSee(route('referrals.destroy', $first->id), false)
            ->assertSee(route('referrals.show', $second->id), false)
            ->assertSee(route('referrals.print', $second->id), false)
            ->assertSee(route('referrals.destroy', $second->id), false);
    }

    public function test_index_status_filter_matches_the_latest_referral_status(): void
    {
        $patient = $this->patient('Filtera');
        $this->referral($patient, [
            'referral_date' => now()->subDays(3)->toDateString(),
            'status' => 'Pending',
        ]);
        $this->referral($patient, [
            'referral_date' => now()->toDateString(),
            'status' => 'Completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->staff)
            ->get(route('referrals.index', ['status' => 'Completed']))
            ->assertOk()
            ->assertSeeText('Filtera Lifecycle');

        $this->actingAs($this->staff)
            ->get(route('referrals.index', ['status' => 'Pending']))
            ->assertOk()
            ->assertDontSeeText('Filtera Lifecycle');
    }

    public function test_index_search_returns_a_single_row_for_a_patient_with_many_referrals(): void
    {
        $patient = $this->patient('Searcha', 'Multiplicity');
        $this->referral($patient, ['referral_date' => now()->subDays(2)->toDateString()]);
        $this->referral($patient, ['referral_date' => now()->subDays(1)->toDateString()]);
        $this->referral($patient, ['referral_date' => now()->toDateString()]);

        $other = $this->patient('Ignora', 'Unrelated');

        $this->actingAs($this->staff)
            ->get(route('referrals.index', ['search' => 'Searcha']))
            ->assertOk()
            ->assertViewHas('referrals', function ($referrals) use ($patient) {
                return $referrals->count() === 1 && $referrals->first()->id === $patient->id;
            })
            ->assertDontSeeText('Ignora Unrelated');
    }

    public function test_archived_referral_leaves_history_and_latest_falls_back_to_the_remaining_row(): void
    {
        $patient = $this->patient('Archiva');
        $keep = $this->referral($patient, [
            'referral_date' => now()->subDays(1)->toDateString(),
            'reason' => 'Referral stays visible',
        ]);
        $archive = $this->referral($patient, [
            'referral_date' => now()->toDateString(),
            'reason' => 'Referral archived away',
        ]);

        $this->actingAs($this->staff)->delete(route('referrals.destroy', $archive->id));
        $this->assertSoftDeleted('referrals', ['id' => $archive->id]);

        $response = $this->actingAs($this->staff)->get(route('referrals.index'));

        $response->assertOk()
            ->assertSeeText('Referral stays visible')
            ->assertDontSeeText('Referral archived away')
            ->assertViewHas('referrals', fn ($referrals) => $referrals->count() === 1);

        expect($patient->fresh()->latestReferral->id)->toBe($keep->id);
    }

    public function test_index_is_accessible_to_staff_and_admin_but_not_guests(): void
    {
        $patient = $this->patient('Access', 'Roles');
        $this->referral($patient);

        $this->get(route('referrals.index'))->assertRedirect(route('login'));
        $this->actingAs($this->staff)->get(route('referrals.index'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('referrals.index'))
            ->assertOk()
            ->assertSeeText('Access Roles');
    }
}
