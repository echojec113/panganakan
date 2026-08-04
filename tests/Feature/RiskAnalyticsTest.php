<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\RiskAnalyticsService;

function riskAnalyticsYear(): int
{
    return (int) \Carbon\Carbon::now()->format('Y');
}

function riskAnalyticsDate(int $month, int $day = 15): string
{
    return \Carbon\Carbon::create(riskAnalyticsYear(), $month, $day)->format('Y-m-d');
}

function riskAnalyticsPatient(string $firstName, string $status = 'ONGOING'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => $status,
    ]);
}

function riskAnalyticsVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => riskAnalyticsDate(7),
        'risk_level' => 'HIGH',
        'hypertension' => false,
        'diabetes' => false,
        'anemia' => false,
    ], $overrides));
}

function riskAnalyticsData(?int $month = null): array
{
    return app(RiskAnalyticsService::class)->get($month);
}

it('serves the risk monitoring page with analytics markers and a full month dropdown', function () {
    $user = User::factory()->create();
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'));

    $response = $this->actingAs($user)->get(route('risk.monitoring'));

    $response->assertOk();
    $response->assertSee('Risk Analytics');
    $response->assertSee('riskHighRiskTrendChart');
    $response->assertSee('riskDistributionChart');
    $response->assertSee('riskAnalyticsMonth');
    $response->assertSee('All Months');
    $response->assertSee('January');
    $response->assertSee('December');
    $response->assertDontSee('riskAnalyticsYear');
});

it('does not render risk analytics on the admin dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'));

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('riskHighRiskTrendChart');
    $response->assertDontSee('Risk Analytics');
});

it('returns a zero-filled twelve-month series for the current year', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(7, 10)]);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['visit_date' => riskAnalyticsDate(7, 20), 'risk_level' => 'LOW']);

    $data = riskAnalyticsData();

    expect($data['year'])->toBe(riskAnalyticsYear());
    expect($data['month'])->toBeNull();
    expect($data['labels'])->toBe([
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ]);
    expect($data['highRiskTrend'])->toBe([0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0]);
    expect($data['riskDistribution']['high'][6])->toBe(1);
    expect($data['riskDistribution']['low'][6])->toBe(1);
});

it('returns a single-month series when a month is selected', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(7, 10), 'risk_level' => 'HIGH']);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['visit_date' => riskAnalyticsDate(7, 20), 'risk_level' => 'LOW']);
    riskAnalyticsVisit(riskAnalyticsPatient('Liza'), ['visit_date' => riskAnalyticsDate(6, 5), 'risk_level' => 'HIGH']);

    $data = riskAnalyticsData(7);

    expect($data['month'])->toBe(7);
    expect($data['labels'])->toBe([\Carbon\Carbon::create(riskAnalyticsYear(), 7, 1)->format('M Y')]);
    expect($data['highRiskTrend'])->toBe([1]);
    expect($data['riskDistribution']['high'])->toBe([1]);
    expect($data['riskDistribution']['low'])->toBe([1]);
});

it('zero-fills all twelve months regardless of the data span and excludes other years', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(3, 10)]);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['visit_date' => riskAnalyticsDate(6, 20)]);
    riskAnalyticsVisit(riskAnalyticsPatient('Old'), ['visit_date' => (riskAnalyticsYear() - 1) . '-01-10']);

    $data = riskAnalyticsData();

    expect(count($data['labels']))->toBe(12);
    expect($data['labels'][0])->toBe('Jan');
    expect($data['labels'][11])->toBe('Dec');
    expect($data['highRiskTrend'][2])->toBe(1);
    expect($data['highRiskTrend'][5])->toBe(1);
    expect(array_sum($data['highRiskTrend']))->toBe(2);
});

it('uses only the latest assessment per patient', function () {
    $patient = riskAnalyticsPatient('Maria');
    riskAnalyticsVisit($patient, ['visit_date' => riskAnalyticsDate(3, 10), 'risk_level' => 'LOW']);
    riskAnalyticsVisit($patient, ['visit_date' => riskAnalyticsDate(7, 10), 'risk_level' => 'HIGH']);

    $data = riskAnalyticsData();

    expect($data['riskDistribution']['high'][6])->toBe(1);
    expect($data['riskDistribution']['low'][6])->toBe(0);
    expect($data['highRiskTrend'][6])->toBe(1);
});

it('excludes soft-deleted visits from analytics', function () {
    $visit = riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(7, 10)]);
    $visit->delete();

    $data = riskAnalyticsData();

    expect(count($data['labels']))->toBe(12);
    expect(array_sum($data['highRiskTrend']))->toBe(0);
    expect($data['riskDistribution']['high'])->toBe(array_fill(0, 12, 0));
    expect($data['summary']['highestHighRiskPeriod'])->toBeNull();
    expect($data['summary']['mostCommonCondition'])->toBeNull();
});

it('excludes non-canonical risk levels from the distribution', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['risk_level' => 'HIGH']);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['risk_level' => 'THE SYSTEM CANNOT FIND THE PATH SPECIFIED.']);

    $data = riskAnalyticsData();

    expect($data['riskDistribution']['high'][6])->toBe(1);
    expect($data['riskDistribution']['low'][6])->toBe(0);
    expect($data['riskDistribution']['incomplete'][6])->toBe(0);
    expect(array_sum($data['highRiskTrend']))->toBe(1);
});

it('counts maternal conditions without double-counting a patient', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['hypertension' => true, 'diabetes' => true]);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['hypertension' => true]);

    $data = riskAnalyticsData();

    expect($data['conditions']['Hypertension'][6])->toBe(2);
    expect($data['conditions']['Diabetes'][6])->toBe(1);
    expect($data['conditions']['Anemia'][6])->toBe(0);
    expect($data['summary']['mostCommonCondition']['name'])->toBe('Hypertension');
});

it('computes bp follow-up urgent, pending repeat, and cleared counts', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['urgency' => 'URGENT_CLINICAL_REVIEW']);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['bp_verification_status' => 'PENDING_REPEAT']);
    riskAnalyticsVisit(riskAnalyticsPatient('Liza'), [
        'bp_verification_status' => 'REPEAT_COMPLETED',
        'repeat_bp_sys' => 132,
        'repeat_bp_dia' => 84,
    ]);
    riskAnalyticsVisit(riskAnalyticsPatient('Bea'), [
        'bp_verification_status' => 'REPEAT_COMPLETED',
        'repeat_bp_sys' => 150,
        'repeat_bp_dia' => 96,
    ]);

    $data = riskAnalyticsData();

    expect($data['bpFollowUp']['urgent'][6])->toBe(1);
    expect($data['bpFollowUp']['pendingRepeat'][6])->toBe(1);
    expect($data['bpFollowUp']['cleared'][6])->toBe(1);
});

it('does not treat NOT_REQUIRED as cleared', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['bp_verification_status' => 'NOT_REQUIRED']);

    $data = riskAnalyticsData();

    expect($data['bpFollowUp']['cleared'][6])->toBe(0);
    expect($data['bpFollowUp']['pendingRepeat'][6])->toBe(0);
});

it('reports the highest high-risk month from the current year trend', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(3, 10)]);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['visit_date' => riskAnalyticsDate(6, 10)]);
    riskAnalyticsVisit(riskAnalyticsPatient('Liza'), ['visit_date' => riskAnalyticsDate(6, 20)]);

    $data = riskAnalyticsData();

    expect($data['summary']['highestHighRiskPeriod']['label'])->toBe('Jun');
    expect($data['summary']['highestHighRiskPeriod']['count'])->toBe(2);
});

it('reports a zero-filled single bucket for a selected month without records', function () {
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(6, 10)]);

    $data = riskAnalyticsData(7);

    expect($data['labels'])->toBe([\Carbon\Carbon::create(riskAnalyticsYear(), 7, 1)->format('M Y')]);
    expect($data['highRiskTrend'])->toBe([0]);
    expect($data['riskDistribution']['high'])->toBe([0]);
    expect($data['summary']['highestHighRiskPeriod'])->toBeNull();
});

it('exposes the analytics via the JSON endpoint', function () {
    $user = User::factory()->create();
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(7, 10)]);

    $response = $this->actingAs($user)->getJson(route('risk.monitoring.analytics', ['month' => 7]));

    $response->assertOk();
    $response->assertJsonPath('year', riskAnalyticsYear());
    $response->assertJsonPath('month', 7);
    $response->assertJsonPath('labels', [\Carbon\Carbon::create(riskAnalyticsYear(), 7, 1)->format('M Y')]);
    $response->assertJsonPath('highRiskTrend', [1]);
});

it('safely defaults invalid month values to all months', function () {
    $user = User::factory()->create();
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['visit_date' => riskAnalyticsDate(7, 10)]);

    $response = $this->actingAs($user)->getJson(route('risk.monitoring.analytics', ['month' => 13]));

    $response->assertOk();
    $response->assertJsonPath('month', null);
    $response->assertJsonCount(12, 'labels');

    $responseText = $this->actingAs($user)->getJson(route('risk.monitoring.analytics', ['month' => 'not-a-month']));

    $responseText->assertOk();
    $responseText->assertJsonPath('month', null);
    $responseText->assertJsonCount(12, 'labels');
});

it('keeps filters functional on the monitoring page', function () {
    $user = User::factory()->create();
    riskAnalyticsVisit(riskAnalyticsPatient('Maria'), ['risk_level' => 'HIGH']);
    riskAnalyticsVisit(riskAnalyticsPatient('Ana'), ['risk_level' => 'LOW']);

    $response = $this->actingAs($user)->get(route('risk.monitoring', ['risk_filter' => 'HIGH']));

    $response->assertOk();
    $response->assertSee('Maria Test');
    $response->assertDontSee('Ana Test');
    $response->assertSee('riskHighRiskTrendChart');
});
