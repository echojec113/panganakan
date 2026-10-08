<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (address autocomplete UI) - static render-level coverage only.
 *
 * The JavaScript behaviors (debounce, AbortController, keyboard, city
 * autofill) are verified by code review; no browser-automation package is
 * installed in this project and installing one is out of scope, so these
 * tests intentionally assert what the server actually renders: input names,
 * ids/labels, the shared partial, the endpoint URL, and that nothing
 * admin-facing (psgc_code, innerHTML usage) leaked into the forms.
 */

function autocompleteUiStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function autocompleteUiPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'birthdate' => today()->subYears(30)->toDateString(),
        'age' => 30,
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 2,
        'para' => 0,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(16)->toDateString(),
        'edd' => today()->subWeeks(16)->addDays(280)->toDateString(),
        'status' => 'ONGOING',
        'address_line' => '123 Ilustre St.',
        'barangay' => 'Poblacion',
        'city_municipality' => 'Makati',
    ], $overrides));
}

function autocompleteUiStorePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Manual',
        'middle_name' => '',
        'last_name' => 'Entry',
        'birthdate' => '1996-04-12',
        'age' => 30,
        'contact_number' => '09171234567',
        'email' => 'manual@example.com',
        'civil_status' => 'Single',
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
        'address_line' => 'Blk 2 Lot 5 Sampaguita',
        'barangay' => 'San Jose',
        'city_municipality' => 'Santa Maria',
    ], $overrides);
}

/**
 * Counts endpoint references in rendered HTML; @json escapes forward
 * slashes, so the content is normalized before counting.
 */
function autocompleteUiEndpointCount(string $content): int
{
    return substr_count(str_replace('\\/', '/', $content), 'locations/search');
}

it('renders the shared address autocomplete partial once on the add-patient form', function () {
    $response = $this->actingAs(autocompleteUiStaff())->get(route('patients.create'));

    $response->assertOk()
        ->assertSee('psgc-ac-panel', false)
        ->assertSee('id="barangay"', false)
        ->assertSee('for="barangay"', false)
        ->assertSee('id="city_municipality"', false)
        ->assertSee('for="city_municipality"', false)
        ->assertSee('name="barangay"', false)
        ->assertSee('name="city_municipality"', false);

    expect(autocompleteUiEndpointCount($response->getContent()))->toBe(1);
});

it('renders the shared partial once on the edit-patient form with prefilled values', function () {
    $patient = autocompleteUiPatient();

    $response = $this->actingAs(autocompleteUiStaff())->get(route('patients.edit', $patient));

    $response->assertOk()
        ->assertSee('psgc-ac-panel', false)
        ->assertSee('value="Poblacion"', false)
        ->assertSee('value="Makati"', false)
        ->assertSee('id="barangay"', false)
        ->assertSee('id="city_municipality"', false);

    expect(autocompleteUiEndpointCount($response->getContent()))->toBe(1);
});

it('keeps a legacy patient editable without a structured address', function () {
    $patient = autocompleteUiPatient([
        'address' => 'Barangay Poblacion, Town Proper',
        'address_line' => null,
        'barangay' => null,
        'city_municipality' => null,
    ]);

    $response = $this->actingAs(autocompleteUiStaff())->get(route('patients.edit', $patient));

    $response->assertOk()
        ->assertSee('psgc-ac-panel', false)
        ->assertSee('Barangay Poblacion, Town Proper', false);

    expect(autocompleteUiEndpointCount($response->getContent()))->toBe(1);
});

it('never adds a psgc_code field or column to the patient forms', function () {
    $staff = autocompleteUiStaff();

    $this->actingAs($staff)->get(route('patients.create'))
        ->assertOk()
        ->assertDontSee('name="psgc_code"', false)
        ->assertDontSee('psgc_code', false);

    $this->actingAs($staff)->get(route('patients.edit', autocompleteUiPatient()))
        ->assertOk()
        ->assertDontSee('name="psgc_code"', false)
        ->assertDontSee('psgc_code', false);

    expect(Schema::hasColumn('patients', 'psgc_code'))->toBeFalse();
});

it('uses textContent only - neither form nor partial uses innerHTML', function () {
    $staff = autocompleteUiStaff();

    $this->actingAs($staff)->get(route('patients.create'))
        ->assertOk()
        ->assertDontSee('innerHTML', false);

    $this->actingAs($staff)->get(route('patients.edit', autocompleteUiPatient()))
        ->assertOk()
        ->assertDontSee('innerHTML', false);
});

it('still stores manually typed addresses unchanged', function () {
    $response = $this->actingAs(autocompleteUiStaff())->post(
        route('patients.store'),
        autocompleteUiStorePayload()
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect($patient->address_line)->toBe('Blk 2 Lot 5 Sampaguita')
        ->and($patient->barangay)->toBe('San Jose')
        ->and($patient->city_municipality)->toBe('Santa Maria');
});

it('requires the same three address fields as before the autocomplete', function () {
    $payload = autocompleteUiStorePayload([
        'address_line' => null,
        'barangay' => null,
        'city_municipality' => null,
    ]);

    $response = $this->actingAs(autocompleteUiStaff())->post(route('patients.store'), $payload);

    $response->assertSessionHasErrors(['address_line', 'barangay', 'city_municipality']);
});
