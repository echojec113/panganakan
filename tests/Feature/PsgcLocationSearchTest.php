<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function psgcSearchUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function psgcSearchPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Snapshot',
        'last_name' => 'Guard',
        'age' => 27,
        'address' => 'Unit test address',
        'contact_number' => '09171234567',
        'email' => 'snapshot.guard@example.com',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

it('allows an authenticated admin to search locations', function () {
    $response = $this->actingAs(psgcSearchUser('admin'))
        ->get(route('locations.search', ['q' => 'muzon south']));

    $response->assertOk()
        ->assertJsonPath('results.0.code', '0301420061')
        ->assertJsonPath('results.0.name', 'Muzon South');
});

it('allows an authenticated staff member to search locations', function () {
    $response = $this->actingAs(psgcSearchUser('staff'))
        ->get(route('locations.search', ['q' => 'muzon south']));

    $response->assertOk()
        ->assertJsonPath('results.0.code', '0301420061');
});

it('denies guests and leaks no location search data', function () {
    $browser = $this->get(route('locations.search', ['q' => 'muzon south']));
    $browser->assertRedirect(route('login'));
    $browser->assertDontSee('"results"', false);
    $browser->assertDontSee('0301420061', false);

    $json = $this->getJson(route('locations.search', ['q' => 'muzon south']));
    $json->assertUnauthorized();
    $json->assertJsonMissingPath('results');
    $json->assertDontSee('0301420061', false);
});

it('returns the exact PSGC record for a valid search', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'muzon south']));

    $response->assertOk();

    expect($response->json('results'))->toBe([
        [
            'code' => '0301420061',
            'name' => 'Muzon South',
            'level' => 'Bgy',
            'parent' => '0301420000',
            'city' => 'City of San Jose Del Monte',
            'label' => 'Muzon South, City of San Jose Del Monte, Bulacan, Central Luzon',
        ],
    ]);
});

it('returns an empty results array for a missing or empty query', function () {
    $user = psgcSearchUser();

    $missing = $this->actingAs($user)->get(route('locations.search'));
    $missing->assertOk()->assertExactJson(['results' => []]);

    $empty = $this->actingAs($user)->get(route('locations.search', ['q' => '']));
    $empty->assertOk()->assertExactJson(['results' => []]);

    $whitespace = $this->actingAs($user)->get(route('locations.search', ['q' => '   ']));
    $whitespace->assertOk()->assertExactJson(['results' => []]);
});

it('returns an empty results array for one-character queries', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 's']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

it('returns an empty results array for unknown locations', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'zzzqqxyzzyplace']));

    $response->assertOk()->assertExactJson(['results' => []]);
});

it('matches queries case-insensitively', function () {
    $user = psgcSearchUser();

    $lower = $this->actingAs($user)->get(route('locations.search', ['q' => 'muzon south']));
    $upper = $this->actingAs($user)->get(route('locations.search', ['q' => 'MUZON SOUTH']));

    $lower->assertOk()->assertJsonPath('results.0.code', '0301420061');
    $upper->assertOk()->assertJsonPath('results.0.code', '0301420061');
    expect($upper->json())->toBe($lower->json());
});

it('handles unicode queries safely', function () {
    $user = psgcSearchUser();

    $accented = $this->actingAs($user)->get(route('locations.search', ['q' => 'Parañaque']));
    $accented->assertOk()
        ->assertJsonPath('results.0.code', '1381000000')
        ->assertJsonPath('results.0.name', 'City of Parañaque');

    $folded = $this->actingAs($user)->get(route('locations.search', ['q' => 'paranaque']));
    $folded->assertOk()->assertJsonPath('results.0.code', '1381000000');

    $emoji = $this->actingAs($user)->get(route('locations.search', ['q' => '🙂🙂']));
    $emoji->assertOk()->assertExactJson(['results' => []]);
});

it('returns at most twelve results', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'san']));

    $response->assertOk();
    expect($response->json('results'))->toHaveCount(12);
});

it('rejects excessively long queries with safe generic JSON', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => str_repeat('a', 101)]));

    $response->assertStatus(422)
        ->assertExactJson(['message' => 'The search query is too long.'])
        ->assertHeader('Content-Type', 'application/json');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain('psgc')
        ->and($response->getContent())->not->toContain('resources')
        ->and($response->getContent())->not->toContain('Exception');
});

it('rejects non-text queries safely', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search') . '?q[]=muzon');

    $response->assertStatus(422)
        ->assertExactJson(['message' => 'The search query must be text.']);
});

it('returns a valid JSON payload with the documented structure', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'muzon']));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $payload = $response->json();
    expect(array_keys($payload))->toBe(['results'])
        ->and($payload['results'])->not->toBeEmpty();

    foreach ($payload['results'] as $row) {
        expect(array_keys($row))->toBe(['code', 'name', 'level', 'parent', 'city', 'label']);
    }
});

it('resolves the parent city name for barangay suggestions without label parsing', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'muzon south']));

    $response->assertOk()
        ->assertJsonPath('results.0.level', 'Bgy')
        ->assertJsonPath('results.0.parent', '0301420000')
        ->assertJsonPath('results.0.city', 'City of San Jose Del Monte');
});

it('resolves sub-municipality districts to their city', function () {
    $response = $this->actingAs(psgcSearchUser())
        ->get(route('locations.search', ['q' => 'tondo i/ii']));

    $response->assertOk()
        ->assertJsonPath('results.0.level', 'SubMun')
        ->assertJsonPath('results.0.city', 'City of Manila');
});

it('treats city and municipality suggestions as their own city', function () {
    $user = psgcSearchUser();

    $city = $this->actingAs($user)->get(route('locations.search', ['q' => 'city of cebu']));
    $city->assertOk()
        ->assertJsonPath('results.0.level', 'City')
        ->assertJsonPath('results.0.city', 'City of Cebu');

    $ncr = $this->actingAs($user)->get(route('locations.search', ['q' => 'caloocan']));
    $ncr->assertOk();
    $cityRow = collect($ncr->json('results'))->firstWhere('code', '1380100000');
    expect($cityRow['city'])->toBe('City of Caloocan');

    $municipality = $this->actingAs($user)->get(route('locations.search', ['q' => 'pateros']));
    $municipality->assertOk()
        ->assertJsonPath('results.0.level', 'Mun')
        ->assertJsonPath('results.0.city', 'Pateros');
});

it('returns a null city for region and province rows', function () {
    $user = psgcSearchUser();

    $province = $this->actingAs($user)->get(route('locations.search', ['q' => 'bulacan']));
    $province->assertOk()
        ->assertJsonPath('results.0.level', 'Prov')
        ->assertJsonPath('results.0.city', null);

    $region = $this->actingAs($user)->get(route('locations.search', ['q' => 'ilocos region']));
    $region->assertOk()
        ->assertJsonPath('results.0.level', 'Reg')
        ->assertJsonPath('results.0.city', null);
});

it('keeps official PSGC codes as strings in the JSON body', function () {
    $user = psgcSearchUser();

    $leadingZero = $this->actingAs($user)->get(route('locations.search', ['q' => 'muzon south']));
    $leadingZero->assertOk();
    expect($leadingZero->json('results.0.code'))->toBe('0301420061')
        ->and(is_string($leadingZero->json('results.0.code')))->toBeTrue();

    $numeric = $this->actingAs($user)->get(route('locations.search', ['q' => 'paranaque']));
    $numeric->assertOk();
    expect(is_string($numeric->json('results.0.code')))->toBeTrue()
        ->and($numeric->json('results.0.code'))->toBe('1381000000');

    // Raw body must quote the code, proving it is emitted as a JSON string.
    $numeric->assertSee('"code":"1381000000"', false);
});

it('registers the search route as GET-only behind auth and no-store middleware', function () {
    $route = Route::getRoutes()->getByName('locations.search');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('locations/search')
        ->and($route->methods())->toEqual(['GET', 'HEAD']);

    // gatherRouteMiddleware reports the registered aliases for this route;
    // the guest-deny tests above prove that `auth` actually enforces access.
    $middleware = app('router')->gatherRouteMiddleware($route);
    expect($middleware)->toContain('auth')
        ->toContain('no-store')
        ->toContain('web');
});

it('never modifies patient records while searching', function () {
    $patient = psgcSearchPatient();
    $before = json_encode(
        DB::table('patients')->orderBy('id')->get()->toArray()
    );

    $user = psgcSearchUser();
    foreach (['muzon south', '', 's', 'Parañaque', 'zzzqqxyzzyplace', str_repeat('a', 101)] as $q) {
        $this->actingAs($user)->get(route('locations.search', ['q' => $q]));
    }

    $after = json_encode(
        DB::table('patients')->orderBy('id')->get()->toArray()
    );

    expect(DB::table('patients')->count())->toBe(1)
        ->and($after)->toBe($before)
        ->and($patient->fresh()->first_name)->toBe('Snapshot');
});
