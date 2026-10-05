<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function latestOnlyStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function latestOnlyPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Latest',
        'middle_name' => 'Only',
        'last_name' => 'Listing',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function latestOnlyVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
    ], $overrides));
}

function latestOnlyTag(Patient $patient): string
{
    return 'PT-' . str_pad((string) $patient->id, 4, '0', STR_PAD_LEFT);
}

function latestOnlyRowCount(string $html): int
{
    return substr_count($html, 'data-label="Patient"');
}

it('shows an ongoing patient with a single active visit exactly once', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1);

    expect(latestOnlyRowCount($response->getContent()))->toBe(1);
    $response->assertSee(latestOnlyTag($patient))
        ->assertSee('Sep 21, 2026');
});

it('shows a patient with multiple active visits once, displaying only the latest visit', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    latestOnlyVisit($patient, ['visit_date' => '2026-09-01']);
    latestOnlyVisit($patient, ['visit_date' => '2026-09-10']);
    $latest = latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1);

    expect(latestOnlyRowCount($response->getContent()))->toBe(1);
    $response->assertSee(latestOnlyTag($patient))
        ->assertSee('Sep 21, 2026')
        ->assertDontSee('Sep 10, 2026')
        ->assertDontSee('Sep 01, 2026')
        ->assertSee(route('prenatal-visits.destroy', $latest->id), false);
});

it('breaks same-date ties by showing the visit with the higher id', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    $first = latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);
    $second = latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    expect(latestOnlyRowCount($response->getContent()))->toBe(1);
    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee(route('prenatal-visits.destroy', $second->id), false)
        ->assertDontSee(route('prenatal-visits.destroy', $first->id), false);
});

it('keeps the clinically newer visit when an older visit is backfilled afterwards', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    $newer = latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);
    $backfilled = latestOnlyVisit($patient, ['visit_date' => '2026-08-15']);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    expect(latestOnlyRowCount($response->getContent()))->toBe(1);
    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee('Sep 21, 2026')
        ->assertSee(route('prenatal-visits.destroy', $newer->id), false)
        ->assertDontSee(route('prenatal-visits.destroy', $backfilled->id), false);
});

it('falls back to the previous visit when the latest visit is archived', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    $previous = latestOnlyVisit($patient, ['visit_date' => '2026-09-01']);
    $latest = latestOnlyVisit($patient, ['visit_date' => '2026-09-10']);
    $latest->delete();

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    expect($latest->trashed())->toBeTrue();
    expect(latestOnlyRowCount($response->getContent()))->toBe(1);
    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee('Sep 01, 2026')
        ->assertDontSee('Sep 10, 2026')
        ->assertSee(route('prenatal-visits.destroy', $previous->id), false)
        ->assertDontSee(route('prenatal-visits.destroy', $latest->id), false);
});

it('removes a patient from the active listing only once every visit is archived', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient();
    $visit = latestOnlyVisit($patient, ['visit_date' => '2026-09-10']);
    $visit->delete();

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 0);
    expect(latestOnlyRowCount($response->getContent()))->toBe(0);
    $response->assertDontSee(latestOnlyTag($patient));

    $archived = $this->actingAs($user)->get(route('prenatal-visits.archived'));
    $archived->assertOk()
        ->assertSee(route('prenatal-visits.restore', $visit->id), false);
});

it('filters on the assessment of the latest visit only', function () {
    $user = latestOnlyStaff();

    $olderHigh = latestOnlyPatient(['first_name' => 'Older', 'middle_name' => 'High', 'last_name' => 'Case']);
    latestOnlyVisit($olderHigh, ['visit_date' => '2026-09-01', 'risk_level' => 'HIGH']);
    latestOnlyVisit($olderHigh, ['visit_date' => '2026-09-10', 'risk_level' => 'LOW']);

    $olderLow = latestOnlyPatient(['first_name' => 'Older', 'middle_name' => 'Low', 'last_name' => 'Case']);
    latestOnlyVisit($olderLow, ['visit_date' => '2026-09-01', 'risk_level' => 'LOW']);
    latestOnlyVisit($olderLow, ['visit_date' => '2026-09-10', 'risk_level' => 'HIGH']);

    $high = $this->actingAs($user)->get(route('prenatal-visits.index', ['risk' => 'HIGH']));
    $high->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee(latestOnlyTag($olderLow))
        ->assertDontSee(latestOnlyTag($olderHigh));
    expect(latestOnlyRowCount($high->getContent()))->toBe(1);

    $low = $this->actingAs($user)->get(route('prenatal-visits.index', ['risk' => 'LOW']));
    $low->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee(latestOnlyTag($olderHigh))
        ->assertDontSee(latestOnlyTag($olderLow));
    expect(latestOnlyRowCount($low->getContent()))->toBe(1);
});

it('returns exactly one row for name and patient id searches', function () {
    $user = latestOnlyStaff();
    $patient = latestOnlyPatient(['first_name' => 'Jerichob', 'middle_name' => 'Dela', 'last_name' => 'Cruz']);
    latestOnlyVisit($patient, ['visit_date' => '2026-09-01']);
    latestOnlyVisit($patient, ['visit_date' => '2026-09-10']);
    latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);

    $byName = $this->actingAs($user)->get(route('prenatal-visits.index', ['search' => 'Jerichob']));
    $byName->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1)
        ->assertSee(latestOnlyTag($patient))
        ->assertSee('Sep 21, 2026');
    expect(latestOnlyRowCount($byName->getContent()))->toBe(1);

    $byPatientId = $this->actingAs($user)->get(route('prenatal-visits.index', ['search' => latestOnlyTag($patient)]));
    $byPatientId->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1);
    expect(latestOnlyRowCount($byPatientId->getContent()))->toBe(1);

    $byNumericId = $this->actingAs($user)->get(route('prenatal-visits.index', ['search' => (string) $patient->id]));
    $byNumericId->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 1);
    expect(latestOnlyRowCount($byNumericId->getContent()))->toBe(1);
});

it('reports the table counter as patients instead of visits', function () {
    $user = latestOnlyStaff();
    foreach (range(1, 3) as $index) {
        $patient = latestOnlyPatient(['first_name' => 'Counter' . $index]);
        latestOnlyVisit($patient, ['visit_date' => '2026-09-01']);
        latestOnlyVisit($patient, ['visit_date' => '2026-09-21']);
    }

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));
    $html = $response->getContent();

    $response->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 3);
    expect(latestOnlyRowCount($html))->toBe(3)
        ->and($html)->toMatch('/<span class="font-medium text-gray-600">\s*3\s*<\/span>\s*patients/')
        ->and($html)->toMatch('/Showing all\s*<span class="font-medium text-gray-700">\s*3\s*<\/span>\s*patients/')
        ->and($html)->not->toMatch('/<span class="font-medium text-gray-600">\s*3\s*<\/span>\s*visits/')
        ->and($html)->not->toMatch('/Showing all\s*<span class="font-medium text-gray-700">\s*3\s*<\/span>\s*visits/');
});

it('paginates at patient level without duplicates and keeps query parameters', function () {
    $user = latestOnlyStaff();
    foreach (range(1, 12) as $index) {
        $patient = latestOnlyPatient(['first_name' => 'Paged' . $index]);
        latestOnlyVisit($patient, ['visit_date' => '2026-09-01', 'risk_level' => 'HIGH']);
        latestOnlyVisit($patient, ['visit_date' => '2026-09-21', 'risk_level' => 'HIGH']);
    }

    $pageOne = $this->actingAs($user)->get(route('prenatal-visits.index', ['risk' => 'HIGH', 'page' => 1]));
    $pageOne->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 12);

    $pageTwo = $this->actingAs($user)->get(route('prenatal-visits.index', ['risk' => 'HIGH', 'page' => 2]));
    $pageTwo->assertOk()
        ->assertViewHas('visits', fn ($pager) => $pager->total() === 12)
        ->assertSee('risk=HIGH');

    preg_match_all('/PT-\d{4}/', $pageOne->getContent(), $firstMatches);
    preg_match_all('/PT-\d{4}/', $pageTwo->getContent(), $secondMatches);
    $firstPageTags = array_unique($firstMatches[0]);
    $secondPageTags = array_unique($secondMatches[0]);

    expect(latestOnlyRowCount($pageOne->getContent()))->toBe(10)
        ->and(latestOnlyRowCount($pageTwo->getContent()))->toBe(2)
        ->and(count($firstPageTags))->toBe(10)
        ->and(count($secondPageTags))->toBe(2)
        ->and(array_intersect($firstPageTags, $secondPageTags))->toBe([]);
});
