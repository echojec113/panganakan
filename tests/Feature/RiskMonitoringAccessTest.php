<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskMonitoringAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_page_but_dashboard_and_analytics_still_work(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $this->get(route('risk.monitoring'))->assertForbidden();
        $this->get(route('risk.monitoring', ['risk_filter' => 'HIGH']))->assertForbidden();
        $this->get(route('dashboard'))->assertOk()
            ->assertDontSee('href="'.route('risk.monitoring').'"', false)
            ->assertSee('staffHighRiskTrendChart', false);
        $this->getJson(route('risk.monitoring.analytics', ['year' => 2026, 'risk_type' => 'LOW']))
            ->assertOk();
    }

    public function test_admin_retains_page_and_navigation_access(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('risk.monitoring'))->assertOk()
            ->assertSee('href="'.route('risk.monitoring').'"', false)
            ->assertViewHas('highRiskCount', 0)
            ->assertViewHas('lowRiskCount', 0);
    }

    public function test_guests_must_log_in(): void
    {
        $this->get(route('risk.monitoring'))->assertRedirect(route('login'));
    }
}
