<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Referral details page: Mark Completed and Cancel Referral use custom
 * DEPLA-styled confirmation modals (matching the Record Referral Refusal
 * modal) instead of native browser confirm(). The existing forms, routes
 * and backend actions are untouched; closed statuses render no modals.
 */
class ReferralShowConfirmationModalsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    private function referral(string $status = 'Pending'): Referral
    {
        $patient = Patient::create([
            'first_name' => 'Modala',
            'last_name' => 'Confirmia',
            'age' => 28,
            'address' => 'Test address',
            'contact_number' => '09171234567',
            'gravida' => 1,
            'para' => 0,
            'status' => 'ONGOING',
        ]);

        return Referral::create([
            'patient_id' => $patient->id,
            'created_by' => $this->staff->id,
            'referred_to' => 'City Hospital',
            'reason' => 'High-risk referral for evaluation',
            'referral_date' => now()->toDateString(),
            'status' => $status,
            'completed_at' => $status === 'Completed' ? now() : null,
        ]);
    }

    public function test_pending_page_replaces_native_confirm_with_custom_modals(): void
    {
        $referral = $this->referral('Pending');

        $response = $this->actingAs($this->staff)
            ->get(route('referrals.show', $referral->id));

        $response->assertOk();

        // Native confirm() is gone entirely (no double confirmation).
        $response->assertDontSee('return confirm(', false);
        $response->assertDontSee('window.confirm(', false);

        // Both custom modals exist with the refusal-modal structure.
        $response->assertSee('id="completeModal"', false);
        $response->assertSee('id="cancelModal"', false);
        $response->assertSee(
            '<div id="completeModal" class="refusal-modal-theme fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6">',
            false
        );
        $response->assertSee(
            '<div id="cancelModal" class="refusal-modal-theme fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6">',
            false
        );

        // Page buttons open the modals instead of submitting directly.
        $response->assertSee('onclick="openCompleteModal()"', false);
        $response->assertSee('onclick="openCancelModal()"', false);

        // Modal content per spec.
        $response->assertSee('Mark Referral as Completed');
        $response->assertSee('Confirm that this referral has been completed. This will update the referral status to Completed.');
        $response->assertSee('Cancel Referral');
        $response->assertSee('Are you sure you want to cancel this referral? A cancelled referral may allow the patient to be referred again if they remain eligible.');

        // Confirm buttons submit the EXISTING page forms (correct referral id).
        $response->assertSee("submitModalForm('completeForm', this)", false);
        $response->assertSee("submitModalForm('cancelForm', this)", false);
        $response->assertSee('id="completeForm"', false);
        $response->assertSee('id="cancelForm"', false);
        $response->assertSee(route('referrals.complete', $referral->id), false);
        $response->assertSee(route('referrals.cancel', $referral->id), false);

        // Close controls never submit.
        $response->assertSee('onclick="closeCompleteModal()"', false);
        $response->assertSee('onclick="closeCancelModal()"', false);

        // Colors match the existing page action buttons.
        $response->assertSee('bg-[#55B85A]', false);   // existing green
        $response->assertSee('bg-gray-700', false);    // existing dark/navy

        // Record Referral Refusal modal remains unchanged/present.
        $response->assertSee('id="refuseModal"', false);
        $response->assertSee('Record Referral Refusal');
        $response->assertSee('openRefuseModal()');
    }

    public function test_modals_and_confirm_are_absent_on_closed_referrals(): void
    {
        foreach (['Completed', 'Refused', 'Cancelled'] as $status) {
            $referral = $this->referral($status);

            $response = $this->actingAs($this->staff)
                ->get(route('referrals.show', $referral->id));

            $response->assertOk();
            $response->assertDontSee('id="completeModal"', false);
            $response->assertDontSee('id="cancelModal"', false);
            $response->assertDontSee('openCompleteModal()', false);
            $response->assertDontSee('openCancelModal()', false);
            $response->assertDontSee('return confirm(', false);
            $response->assertDontSee('Mark Referral as Completed');
        }
    }

    public function test_double_submission_guard_present_on_modal_submit(): void
    {
        $referral = $this->referral('Pending');

        $response = $this->actingAs($this->staff)
            ->get(route('referrals.show', $referral->id));

        $response->assertOk()
            ->assertSee("form.dataset.submitting === '1'", false)
            ->assertSee('form.requestSubmit();', false);
    }
}
