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
            ->assertSee('id="topHighRiskConditionsChart"', false)
            ->assertSee('Reporting Filters')
            ->assertSee('id="selectedRiskAssessments"', false)
            ->assertSee('id="incompleteAssessments"', false)
            ->assertDontSee('id="riskAnalyticsBreakdown"  hidden', false)
            ->assertDontSee('Current Risk Status')
            ->assertDontSee('patient-assessments', false)
            ->assertDontSee('Search Patient');
    }

    public function test_guests_must_log_in(): void
    {
        $this->get(route('risk.monitoring'))->assertRedirect(route('login'));
    }

    public function test_analytics_only_page_supports_year_month_and_risk_type(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get(route('risk.monitoring', ['year' => 2024, 'month' => 2, 'risk_type' => 'LOW']))
            ->assertOk()
            ->assertSee('Risk trends and factors affecting patient risk classification')
            ->assertSee('id="riskAnalyticsYear"', false)
            ->assertSee('id="riskAnalyticsBreakdown"  hidden', false)
            ->assertDontSee('patient-assessments', false)
            ->assertDontSee('id="ageDistributionChart"', false)
            ->assertDontSee('id="riskConditionsChart"', false)
            ->assertViewHas('availableYears', fn ($years) => $years->contains(2024) && $years->contains((int) now()->year))
            ->assertViewHas('analytics', fn ($data) => $data['riskType'] === 'LOW'
                && $data['trend']['granularity'] === 'day'
                && count($data['trend']['labels']) === 29);

        $this->getJson(route('risk.monitoring.analytics', ['year' => 2024, 'risk_type' => 'HIGH']))
            ->assertOk()
            ->assertJsonPath('year', 2024)
            ->assertJsonPath('riskType', 'HIGH')
            ->assertJsonPath('trend.granularity', 'month')
            ->assertJsonCount(12, 'trend.labels');

        $this->getJson(route('risk.monitoring.analytics', ['year' => 2024, 'month' => 2, 'risk_type' => 'LOW']))
            ->assertOk()
            ->assertJsonPath('riskType', 'LOW')
            ->assertJsonPath('trend.granularity', 'day')
            ->assertJsonCount(29, 'trend.labels');
    }

    public function test_selected_period_metrics_use_the_existing_latest_assessment_population(): void
    {
        $addPatient = function (array $history): void {
            $patient = \App\Models\Patient::create([
                'first_name' => 'Analytics', 'last_name' => 'Test', 'age' => 25,
                'address' => 'Test', 'contact_number' => '09171234567',
                'gravida' => 1, 'para' => 0, 'status' => 'ONGOING',
            ]);
            foreach ($history as [$date, $risk]) {
                \App\Models\PrenatalVisit::create([
                    'patient_id' => $patient->id, 'visit_date' => $date, 'risk_level' => $risk,
                ]);
            }
        };
        $addPatient([['2026-07-01', 'HIGH'], ['2026-07-10', 'HIGH']]);
        $addPatient([['2026-07-02', 'ASSESSMENT INCOMPLETE'], ['2026-07-11', 'LOW']]);
        $addPatient([['2026-07-03', 'ASSESSMENT INCOMPLETE']]);
        $addPatient([['2026-08-03', 'ASSESSMENT INCOMPLETE']]);
        $addPatient([['2025-07-03', 'ASSESSMENT INCOMPLETE']]);
        // A later assessment excludes the older July HIGH from July analytics.
        $addPatient([['2026-07-04', 'HIGH'], ['2026-08-04', 'LOW']]);
        // Backfilled higher ID must not replace the clinically latest assessment.
        $addPatient([['2026-07-20', 'HIGH'], ['2026-06-01', 'LOW']]);

        $service = app(\App\Services\RiskAnalyticsService::class);
        $high = $service->get(2026, 7, 'HIGH');
        $low = $service->get(2026, 7, 'LOW');
        $yearly = $service->get(2026, null, 'HIGH');
        $this->assertSame(2, $high['summary']['selectedRiskAssessments']);
        $this->assertSame(1, $low['summary']['selectedRiskAssessments']);
        $this->assertSame(1, $high['summary']['incompleteAssessments']);
        $this->assertSame(1, $low['summary']['incompleteAssessments']);
        $this->assertSame(2, $yearly['summary']['incompleteAssessments']);
        $this->assertSame(1, $service->get(2026, 8, 'HIGH')['summary']['incompleteAssessments']);
        $this->assertSame(1, $service->get(2025, null, 'HIGH')['summary']['incompleteAssessments']);
        $this->assertSame(0, $service->get(2024, null, 'HIGH')['summary']['incompleteAssessments']);
        $expectedDaily = array_fill(0, 31, 0);
        $expectedDaily[9] = $expectedDaily[19] = 1;
        $this->assertSame($expectedDaily, $high['trend']['data']);
        $expectedMonthly = array_fill(0, 12, 0);
        $expectedMonthly[6] = 2;
        $this->assertSame($expectedMonthly, $yearly['trend']['data']);
        $this->assertSame('day', $high['trend']['granularity']);
        $this->assertSame('month', $yearly['trend']['granularity']);
    }
}
