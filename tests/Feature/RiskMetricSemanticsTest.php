<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\RiskAnalyticsService;
use App\Services\RiskMonitoringDataService;
use Illuminate\Http\Request;

function metricPatient(array $attributes = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Metric', 'last_name' => 'Patient', 'age' => 25,
        'address' => 'Test', 'contact_number' => '09171234567',
        'gravida' => 1, 'para' => 0, 'status' => 'ONGOING',
    ], $attributes));
}

function metricVisit(Patient $patient, array $attributes = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id, 'visit_date' => '2026-09-20',
        'risk_level' => 'HIGH', 'next_visit_date' => '2026-09-25',
    ], $attributes));
}

class RiskMetricSemanticsTest extends \Tests\TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    $this->travelTo(\Carbon\Carbon::parse('2026-09-27 12:00:00'));
    $this->withoutVite();
    }

public function test_latest_consumers_use_clinical_date_ties_and_soft_deletes(): void {
    $patient = metricPatient();
    metricVisit($patient);
    $latest = metricVisit($patient, ['risk_level' => 'LOW']);
    metricVisit($patient, ['visit_date' => '2026-08-01']); // Backfilled, higher ID.
    $deleted = metricVisit($patient, ['visit_date' => '2026-09-26']);
    $deleted->delete();

    expect(PrenatalVisit::whereIn('id', PrenatalVisit::latestAssessmentIds())->pluck('id')->all())->toBe([$latest->id]);
    expect($patient->latestPrenatalAssessment->id)->toBe($latest->id);
    expect(app(RiskMonitoringDataService::class)->visits(Request::create('/'))->pluck('id')->all())->toBe([$latest->id]);
    $analytics = app(RiskAnalyticsService::class)->get(2026);
    expect(array_sum($analytics['highRiskTrend']))->toBe(0);
    expect(array_sum($analytics['lowRiskTrend']))->toBe(1);

    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->get(route('dashboard'))->assertOk()
        ->assertViewHas('staffHighRiskCount', 0)->assertViewHas('staffLowRiskCount', 1);
    $this->get(route('risk.monitoring'))->assertForbidden();
    $this->get(route('patients.index'))->assertOk()->assertViewHas('highRiskCount', 0);
}

public function test_status_scopes_and_uncapped_overdue_count(): void {
    $staff = User::factory()->create(['role' => 'staff']);
    foreach (range(1, 7) as $index) {
        metricVisit(metricPatient(['assigned_staff_id' => $index === 1 ? $staff->id : null]));
    }
    metricVisit(metricPatient(['status' => 'DELIVERED']));
    metricVisit(metricPatient(['status' => 'REFERRED']));
    metricVisit(metricPatient(), ['next_visit_date' => '2026-09-27']);
    metricVisit(metricPatient(), ['next_visit_date' => null]);
    $archived = metricPatient();
    metricVisit($archived);
    $archived->delete();

    $this->actingAs($staff)->get(route('dashboard'))->assertOk()
        ->assertViewHas('staffHighRiskCount', 9)
        ->assertViewHas('highRiskAlerts', fn ($rows) => $rows->count() === 9)
        ->assertViewHas('followUpOverdueCount', 7);
    $this->get(route('patients.index'))->assertOk()->assertViewHas('highRiskCount', 9);
    $this->get(route('patients.index', ['filter' => 'my']))->assertOk()->assertViewHas('highRiskCount', 1);
    $this->get(route('prenatal-visits.index'))->assertOk()
        ->assertViewHas('visits', fn ($rows) => $rows->count() === 9);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()
        ->assertViewHas('highRisk', 11)
        ->assertViewHas('overdueCount', 7)
        ->assertViewHas('overdueFollowUps', fn ($rows) => $rows->count() === 5);
}

public function test_staff_low_and_incomplete_counts_only_include_ongoing_patients(): void {
    foreach (['ONGOING', 'DELIVERED', 'REFERRED'] as $status) {
        foreach (['LOW', 'ASSESSMENT INCOMPLETE'] as $risk) {
            metricVisit(metricPatient(['status' => $status]), ['risk_level' => $risk]);
        }
    }
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('dashboard'))->assertOk()
        ->assertViewHas('staffLowRiskCount', 1)
        ->assertViewHas('staffIncompleteCount', 1);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('dashboard'))->assertOk()
        ->assertViewHas('lowRisk', 3)
        ->assertViewHas('incompleteCount', 3);
}

public function test_monitoring_overdue_uses_calendar_dates_and_preserves_status_exclusions(): void {
    foreach (['ONGOING', 'DELIVERED', 'REFERRED'] as $status) {
        $patient = metricPatient(['status' => $status]);
        foreach ([null, '2026-09-26', '2026-09-27', '2026-09-28'] as $date) {
            $visit = metricVisit($patient, ['next_visit_date' => $date]);
            $this->assertSame(
                $status === 'ONGOING' && $date === '2026-09-26',
                $visit->isMonitoringOverdue()
            );
        }
    }
}

public function test_visit_counts_and_calendar_month_bounds(): void {
    $patient = metricPatient();
    foreach (['2025-09-20', '2026-08-31', '2026-09-01', '2026-09-30', '2026-10-01'] as $date) {
        metricVisit($patient, ['visit_date' => $date]);
    }
    $deleted = metricVisit($patient);
    $deleted->delete();
    $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->get(route('prenatal-visits.index'))->assertOk()
        ->assertViewHas('visits', fn ($rows) => $rows->count() === 5);
    expect($response->getContent())->toMatch('/This Month<\/p>\s*<p[^>]*>2<\/p>/');
}

}
