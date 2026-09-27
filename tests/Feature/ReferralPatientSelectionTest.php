<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralPatientSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name, string $status = 'ONGOING'): Patient
    {
        return Patient::create([
            'first_name' => $name, 'last_name' => 'Selection', 'age' => 28,
            'address' => 'Test address', 'contact_number' => '09171234567',
            'gravida' => 1, 'para' => 0, 'status' => $status,
        ]);
    }

    public function test_staff_can_select_only_active_ongoing_patients(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $ongoing = $this->patient('Ongoing');
        $this->patient('Delivered', 'DELIVERED');
        $this->patient('Legacy', 'REFERRED');
        $trashed = $this->patient('Trashed');
        // Avoid invoking unrelated patient cascade hooks in this read-path test.
        Patient::whereKey($trashed->id)->update(['deleted_at' => now()]);

        $response = $this->actingAs($staff)->get(route('referrals.select-patient'));
        $response->assertOk()->assertSee(route('referrals.create', $ongoing->id), false);
        $response->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === [$ongoing->id]);
        $this->get(route('referrals.create', $ongoing->id))->assertOk()
            ->assertViewHas('patient', fn ($patient) => $patient->id === $ongoing->id);
        $this->assertDatabaseCount('referrals', 0);
    }

    public function test_search_preserves_ongoing_filter(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $match = $this->patient('Maria');
        $this->patient('Maria', 'DELIVERED');
        $this->patient('Other');

        $this->actingAs($staff)->get(route('referrals.select-patient', ['search' => 'Maria']))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->pluck('id')->all() === [$match->id]);
    }

    public function test_guest_and_admin_cannot_access_selector(): void
    {
        $this->get(route('referrals.select-patient'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('referrals.select-patient'))->assertForbidden();
    }

    public function test_header_link_is_staff_only(): void
    {
        $url = route('referrals.select-patient');
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('referrals.index'))->assertOk()->assertSee($url, false);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('referrals.index'))->assertOk()->assertDontSee($url, false);
    }

    public function test_empty_selector_renders_helpful_message(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('referrals.select-patient'))->assertOk()
            ->assertSee('No ongoing pregnancies are available for referral.');
    }
}
