<?php

use App\Models\Patient;
use App\Models\Referral;
use App\Models\User;
use App\Services\ReferralAnalyticsService;

function referralAnalyticsYear(): int
{
    return (int) \Carbon\Carbon::now()->format('Y');
}

function referralAnalyticsDate(int $month, int $day = 7): string
{
    return \Carbon\Carbon::create(referralAnalyticsYear(), $month, $day)->format('Y-m-d');
}

function referralAnalyticsPatient(string $firstName): Patient
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
        'status' => 'REFERRED',
    ]);
}

function referralAnalyticsCreate(User $user, Patient $patient, array $overrides = []): Referral
{
    return Referral::create(array_merge([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'Provincial Hospital',
        'reason' => 'Needs specialist care',
        'referral_date' => referralAnalyticsDate(7),
        'status' => 'Pending',
    ], $overrides));
}

function referralAnalyticsData(?int $month = null): array
{
    return app(ReferralAnalyticsService::class)->get($month);
}

it('serves the referrals page with analytics markers and a full month dropdown', function () {
    $user = User::factory()->create();
    referralAnalyticsCreate($user, referralAnalyticsPatient('Maria'));

    $response = $this->actingAs($user)->get(route('referrals.index'));

    $response->assertOk();
    $response->assertSee('Referral Analytics');
    $response->assertSee('referralTrendChart');
    $response->assertSee('referralDestinationsChart');
    $response->assertSee('referralAnalyticsMonth');
    $response->assertSee('All Months');
    $response->assertSee('January');
    $response->assertSee('December');
    $response->assertDontSee('referralAnalyticsYear');
});

it('does not render referral analytics on the admin dashboard', function () {
    $user = User::factory()->create(['role' => 'admin']);
    referralAnalyticsCreate($user, referralAnalyticsPatient('Maria'));

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertDontSee('referralTrendChart');
    $response->assertDontSee('Referral Analytics');
});

it('returns a zero-filled twelve-month series for the current year', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(7, 7), 'status' => 'Pending']);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(7, 20), 'status' => 'Completed']);

    $data = referralAnalyticsData();

    expect($data['year'])->toBe(referralAnalyticsYear());
    expect($data['month'])->toBeNull();
    expect($data['labels'])->toBe([
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ]);
    expect($data['referralTrend'])->toBe([0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 0, 0]);
    expect($data['statusTrend']['pending'][6])->toBe(1);
    expect($data['statusTrend']['completed'][6])->toBe(1);
});

it('returns a single-month series when a month is selected', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(7, 7), 'status' => 'Pending']);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(7, 20), 'status' => 'Completed']);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(6, 1)]);

    $data = referralAnalyticsData(7);

    expect($data['month'])->toBe(7);
    expect($data['labels'])->toBe([\Carbon\Carbon::create(referralAnalyticsYear(), 7, 1)->format('M Y')]);
    expect($data['referralTrend'])->toBe([2]);
    expect($data['statusTrend']['pending'])->toBe([1]);
    expect($data['statusTrend']['completed'])->toBe([1]);
});

it('always includes all twelve months of the current year and excludes other years', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referral_date' => (referralAnalyticsYear() - 1) . '-01-07']);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(7)]);

    $data = referralAnalyticsData();

    expect(count($data['labels']))->toBe(12);
    expect($data['labels'][0])->toBe('Jan');
    expect($data['labels'][11])->toBe('Dec');
    expect(array_sum($data['referralTrend']))->toBe(1);
});

it('computes the yearly trend and busiest month across the current year', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(3)]);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(6, 7)]);
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(6, 20)]);

    $data = referralAnalyticsData();

    expect($data['referralTrend'][2])->toBe(1);
    expect($data['referralTrend'][5])->toBe(2);
    expect(array_sum($data['referralTrend']))->toBe(3);
    expect($data['summary']['busiestPeriod']['label'])->toBe('Jun');
    expect($data['summary']['busiestPeriod']['count'])->toBe(2);
});

it('groups destinations and reasons case-insensitively without altering stored values', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referred_to' => 'Sa Muzon', 'reason' => 'Hindi kaya ihandle']);
    referralAnalyticsCreate($user, $patient, ['referred_to' => '  sa   muzon ', 'reason' => 'Hindi Kaya ihandle']);
    referralAnalyticsCreate($user, $patient, ['referred_to' => 'Provincial Hospital', 'reason' => 'Needs specialist care']);

    $data = referralAnalyticsData();

    $destinations = collect($data['destinations'])->keyBy(fn ($d) => strtolower(trim($d['label'])));
    expect($destinations['sa muzon']['count'])->toBe(2);

    $reasons = collect($data['reasons'])->keyBy(fn ($r) => strtolower(trim($r['label'])));
    expect($reasons['hindi kaya ihandle']['count'])->toBe(2);

    expect(Referral::where('referred_to', '  sa   muzon ')->count())->toBe(1);
    expect(Referral::where('referred_to', 'Sa Muzon')->count())->toBe(1);
});

it('caps destinations and reasons at eight entries', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');

    for ($i = 1; $i <= 12; $i++) {
        referralAnalyticsCreate($user, $patient, [
            'referred_to' => 'Hospital ' . $i,
            'reason' => 'Reason ' . $i,
        ]);
    }

    $data = referralAnalyticsData();

    expect(count($data['destinations']))->toBeLessThanOrEqual(8);
    expect(count($data['reasons']))->toBeLessThanOrEqual(8);
});

it('computes completion rate from pending and completed referrals', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['status' => 'Pending']);
    referralAnalyticsCreate($user, $patient, ['status' => 'Pending']);
    referralAnalyticsCreate($user, $patient, ['status' => 'Completed']);

    $data = referralAnalyticsData(7);

    expect($data['summary']['completionRate'])->toBe(33.3);
});

it('reports a zero completion rate and empty summary when there are no referrals', function () {
    $data = referralAnalyticsData();

    expect($data['summary']['completionRate'])->toBe(0.0);
    expect($data['summary']['mostReferredHospital'])->toBeNull();
    expect($data['summary']['busiestPeriod'])->toBeNull();
    expect($data['summary']['mostCommonReason'])->toBeNull();
    expect(count($data['labels']))->toBe(12);
    expect($data['labels'][0])->toBe('Jan');
    expect($data['labels'][11])->toBe('Dec');
    expect(array_sum($data['referralTrend']))->toBe(0);
});

it('returns a single zero-filled bucket for a selected month without data', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient, ['referral_date' => referralAnalyticsDate(6)]);

    $data = referralAnalyticsData(7);

    expect($data['labels'])->toBe([\Carbon\Carbon::create(referralAnalyticsYear(), 7, 1)->format('M Y')]);
    expect($data['referralTrend'])->toBe([0]);
    expect($data['destinations'])->toBe([]);
    expect($data['summary']['completionRate'])->toBe(0.0);
    expect($data['summary']['mostReferredHospital'])->toBeNull();
});

it('exposes the analytics via the JSON endpoint', function () {
    $user = User::factory()->create();
    referralAnalyticsCreate($user, referralAnalyticsPatient('Maria'), ['referral_date' => referralAnalyticsDate(7)]);

    $response = $this->actingAs($user)->getJson(route('referrals.analytics', ['month' => 7]));

    $response->assertOk();
    $response->assertJsonPath('year', referralAnalyticsYear());
    $response->assertJsonPath('month', 7);
    $response->assertJsonPath('labels', [\Carbon\Carbon::create(referralAnalyticsYear(), 7, 1)->format('M Y')]);
    $response->assertJsonPath('referralTrend', [1]);
});

it('safely defaults invalid month values to all months', function () {
    $user = User::factory()->create();
    referralAnalyticsCreate($user, referralAnalyticsPatient('Maria'), ['referral_date' => referralAnalyticsDate(7)]);

    $response = $this->actingAs($user)->getJson(route('referrals.analytics', ['month' => 13]));

    $response->assertOk();
    $response->assertJsonPath('month', null);
    $response->assertJsonCount(12, 'labels');

    $responseText = $this->actingAs($user)->getJson(route('referrals.analytics', ['month' => 'not-a-month']));

    $responseText->assertOk();
    $responseText->assertJsonPath('month', null);
    $responseText->assertJsonCount(12, 'labels');
});

it('keeps existing search, print, and complete actions functional', function () {
    $user = User::factory()->create();
    $patient = referralAnalyticsPatient('Maria');
    referralAnalyticsCreate($user, $patient);

    $response = $this->actingAs($user)->get(route('referrals.index', ['search' => 'Maria']));

    $response->assertOk();
    $response->assertSee('Maria');
    $response->assertSee(route('referrals.print', Referral::first()->id));
    $response->assertSee('Complete');
    $response->assertSee('referralTrendChart');
});
