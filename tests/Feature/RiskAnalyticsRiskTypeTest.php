<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\RiskAnalyticsService;

function riskTypeYear(): int
{
    return (int) \Carbon\Carbon::now()->format('Y');
}

function riskTypeDate(int $month, int $day = 15): string
{
    return \Carbon\Carbon::create(riskTypeYear(), $month, $day)->format('Y-m-d');
}

function riskTypePatient(string $firstName): Patient
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
        'status' => 'ONGOING',
    ]);
}

function riskTypeVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => riskTypeDate(7),
        'risk_level' => 'HIGH',
        'hypertension' => false,
        'diabetes' => false,
        'anemia' => false,
    ], $overrides));
}

function riskTypeData(?int $month = null, ?string $riskType = null): array
{
    return app(RiskAnalyticsService::class)->get($month, $riskType);
}

it('defaults the analytics risk type to HIGH when none is given', function () {
    riskTypeVisit(riskTypePatient('Maria'), ['risk_level' => 'HIGH']);
    riskTypeVisit(riskTypePatient('Ana'), ['risk_level' => 'LOW']);

    $data = riskTypeData();

    expect($data['riskType'])->toBe('HIGH');
    expect($data['riskTrend'])->toBe($data['highRiskTrend']);
    expect(array_sum($data['riskTrend']))->toBe(1);
    expect(array_sum($data['lowRiskTrend']))->toBe(1);
});

it('returns the low-risk trend when LOW is requested', function () {
    riskTypeVisit(riskTypePatient('Maria'), ['risk_level' => 'HIGH']);
    riskTypeVisit(riskTypePatient('Ana'), ['risk_level' => 'LOW']);

    $data = riskTypeData(null, 'LOW');

    expect($data['riskType'])->toBe('LOW');
    expect($data['riskTrend'])->toBe($data['lowRiskTrend']);
    expect(array_sum($data['riskTrend']))->toBe(1);
    expect(array_sum($data['highRiskTrend']))->toBe(1);
});

it('treats any non-HIGH/LOW risk type as HIGH', function () {
    riskTypeVisit(riskTypePatient('Maria'), ['risk_level' => 'LOW']);

    $data = riskTypeData(null, 'MEDIUM');

    expect($data['riskType'])->toBe('HIGH');
    expect($data['riskTrend'])->toBe($data['highRiskTrend']);
});

it('combines month and risk type filters in the service', function () {
    riskTypeVisit(riskTypePatient('Maria'), ['visit_date' => riskTypeDate(7, 10), 'risk_level' => 'HIGH']);
    riskTypeVisit(riskTypePatient('Ana'), ['visit_date' => riskTypeDate(7, 20), 'risk_level' => 'LOW']);

    $data = riskTypeData(7, 'LOW');

    expect($data['month'])->toBe(7);
    expect($data['riskType'])->toBe('LOW');
    expect($data['riskTrend'])->toBe([1]);
    expect($data['lowRiskTrend'])->toBe([1]);
});

it('exposes the risk type via the JSON analytics endpoint', function () {
    $user = User::factory()->create();
    riskTypeVisit(riskTypePatient('Maria'), ['risk_level' => 'HIGH']);
    riskTypeVisit(riskTypePatient('Ana'), ['risk_level' => 'LOW']);

    $response = $this->actingAs($user)->getJson(route('risk.monitoring.analytics', ['risk_type' => 'LOW']));

    $response->assertOk();
    $response->assertJsonPath('riskType', 'LOW');
    $response->assertJsonPath('riskTrend', $response->json('lowRiskTrend'));
});

it('renders the risk type selector defaulting to High Risk on the monitoring page', function () {
    $user = User::factory()->create();
    riskTypeVisit(riskTypePatient('Maria'));

    $response = $this->actingAs($user)->get(route('risk.monitoring'));

    $response->assertOk();
    $response->assertSee('riskAnalyticsType');
    $response->assertSee('riskTrendTitle');
    $response->assertSee('High-Risk Patients');
    $response->assertSee('option value="HIGH" selected', false);
    $response->assertSee('Risk Analytics Breakdown');
});

it('keeps the summary cards and other analytics graphs after the risk trend', function () {
    $user = User::factory()->create();
    riskTypeVisit(riskTypePatient('Maria'));

    $response = $this->actingAs($user)->get(route('risk.monitoring'));

    $html = $response->getContent();
    $trendPos = strpos($html, 'riskHighRiskTrendChart');
    $breakdownPos = strpos($html, 'riskDistributionChart');
    $bpPos = strpos($html, 'riskBpFollowUpChart');

    expect($trendPos)->toBeGreaterThan(0);
    expect($breakdownPos)->toBeGreaterThan($trendPos);
    expect($bpPos)->toBeGreaterThan($breakdownPos);
    expect($html)->toContain('Highest High-Risk Month');
    expect($html)->toContain('Most Common Condition');
    expect($html)->toContain('HIGH Risk');
    expect($html)->toContain('LOW Risk');
});
