<?php

use App\Models\Patient;
use App\Models\User;

/**
 * Panel rule: when Para = 0, Previous CS must be No (0) and rejected when
 * explicitly submitted as Yes (1). Para >= 1 allows either value.
 *
 * The frontend disables the Previous CS dropdown at Para = 0, but these
 * tests exercise the server-side rule directly (never trusting the UI).
 */

function previousCsStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function previousCsStorePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana',
        'middle_name' => '',
        'last_name' => 'Reyes',
        'birthdate' => '1995-01-01',
        'age' => 31,
        'address_line' => '123 Mabini St.',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
        'contact_number' => '09171234567',
        'email' => 'ana@example.com',
        'civil_status' => 'Single',
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
    ], $overrides);
}

function previousCsPatientFixture(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Legacy',
        'last_name' => 'Patient',
        'birthdate' => today()->subYears(28)->toDateString(),
        'age' => 28,
        'address' => 'Santa Maria',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
        'status' => 'ONGOING',
    ], $overrides));
}

function previousCsUpdatePayload(Patient $patient, array $overrides = []): array
{
    return array_merge([
        'first_name' => $patient->first_name,
        'middle_name' => '',
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate->format('Y-m-d'),
        'age' => $patient->age,
        'address' => $patient->address,
        'contact_number' => $patient->contact_number,
        'civil_status' => $patient->civil_status,
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => $patient->gravida,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => $patient->miscarriage,
        'lmp' => $patient->lmp->format('Y-m-d'),
        'edd' => $patient->edd->format('Y-m-d'),
    ], $overrides);
}

// ---------------------------------------------------------------------------
// store()
// ---------------------------------------------------------------------------

it('accepts para 0 with previous cs 0 on create', function () {
    $staff = previousCsStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        previousCsStorePayload(['para' => 0, 'previous_cs' => '0'])
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect($patient)->not->toBeNull()
        ->and((int) $patient->para)->toBe(0)
        ->and((int) $patient->previous_cs)->toBe(0);
});

it('rejects para 0 with previous cs 1 on create', function () {
    $staff = previousCsStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        previousCsStorePayload(['para' => 0, 'previous_cs' => '1'])
    );

    $response->assertSessionHasErrors([
        'previous_cs' => 'Previous CS cannot be Yes when Para is 0.',
    ]);

    expect(Patient::count())->toBe(0);
});

it('accepts para 1 with previous cs 0 on create', function () {
    $staff = previousCsStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        previousCsStorePayload(['gravida' => 2, 'para' => 1, 'previous_cs' => '0'])
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect((int) $patient->para)->toBe(1)
        ->and((int) $patient->previous_cs)->toBe(0);
});

it('accepts para 1 with previous cs 1 on create', function () {
    $staff = previousCsStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        previousCsStorePayload(['gravida' => 2, 'para' => 1, 'previous_cs' => '1'])
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect((int) $patient->para)->toBe(1)
        ->and((int) $patient->previous_cs)->toBe(1);
});

it('keeps the existing required validation when previous cs is missing on create', function () {
    $staff = previousCsStaff();

    $payload = previousCsStorePayload();
    unset($payload['previous_cs']);

    $response = $this->actingAs($staff)->post(route('patients.store'), $payload);

    $response->assertSessionHasErrors('previous_cs');

    expect(Patient::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// update()
// ---------------------------------------------------------------------------

it('accepts para 0 with previous cs 0 on update', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(['para' => 1, 'previous_cs' => 1]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), previousCsUpdatePayload($patient, [
            'para' => 0,
            'previous_cs' => '0',
        ]));

    $response->assertRedirect(route('patients.index'));

    expect((int) $patient->fresh()->para)->toBe(0)
        ->and((int) $patient->fresh()->previous_cs)->toBe(0);
});

it('rejects para 0 with previous cs 1 on update and leaves the record unchanged', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(['para' => 1, 'previous_cs' => 1]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), previousCsUpdatePayload($patient, [
            'para' => 0,
            'previous_cs' => '1',
        ]));

    $response->assertSessionHasErrors([
        'previous_cs' => 'Previous CS cannot be Yes when Para is 0.',
    ]);

    $patient->refresh();

    expect((int) $patient->para)->toBe(1)
        ->and((int) $patient->previous_cs)->toBe(1);
});

it('accepts para 1 with previous cs 0 on update', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(['gravida' => 2, 'para' => 1, 'previous_cs' => 0]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), previousCsUpdatePayload($patient, [
            'gravida' => 2,
            'para' => 1,
            'previous_cs' => '0',
        ]));

    $response->assertRedirect(route('patients.index'));

    expect((int) $patient->fresh()->previous_cs)->toBe(0);
});

it('accepts para 1 with previous cs 1 on update', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(['gravida' => 2, 'para' => 1, 'previous_cs' => 0]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), previousCsUpdatePayload($patient, [
            'gravida' => 2,
            'para' => 1,
            'previous_cs' => '1',
        ]));

    $response->assertRedirect(route('patients.index'));

    expect((int) $patient->fresh()->previous_cs)->toBe(1);
});

it('keeps the existing required validation when previous cs is missing on update', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture();

    $payload = previousCsUpdatePayload($patient);
    unset($payload['previous_cs']);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), $payload);

    $response->assertSessionHasErrors('previous_cs');
});

// ---------------------------------------------------------------------------
// UI wiring (server-rendered assertions; runtime JS behaviour is verified
// manually because these tests do not execute JavaScript)
// ---------------------------------------------------------------------------

it('renders the previous cs select with its conditional script on the create page', function () {
    $staff = previousCsStaff();

    $response = $this->actingAs($staff)->get(route('patients.create'));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('id="previous_cs"')
        ->toContain('syncPreviousCsToPara')
        ->toContain('preparePreviousCsForSubmit');
});

it('renders the previous cs select with its conditional script on the edit page', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(); // para = 0

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('id="previous_cs"')
        ->toContain('syncPreviousCsToPara')
        ->toContain('preparePreviousCsForSubmit');
});

it('wires para change events to the previous cs sync so para 1 to 0 resets the dropdown to No', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture();

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $content = $response->getContent();

    // para input drives the sync on both input and change events ...
    expect($content)
        ->toContain('paraInput.addEventListener("input", syncPreviousCsToPara)')
        ->toContain('paraInput.addEventListener("change", syncPreviousCsToPara)')
        // ... the sync runs once on page load (covers repopulated old() values) ...
        ->toContain('syncPreviousCsToPara();')
        // ... and when Para is not >= 1 the dropdown is forced to No (0) and disabled.
        ->toContain("previousCsSelect.value = '0';")
        ->toContain('previousCsSelect.disabled = true;');
});

it('surfaces the previous cs validation message on the edit page after a rejected update', function () {
    $staff = previousCsStaff();
    $patient = previousCsPatientFixture(['para' => 1, 'previous_cs' => 1]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), previousCsUpdatePayload($patient, [
            'para' => 0,
            'previous_cs' => '1',
        ]));

    $response->assertSessionHasErrors('previous_cs');

    $editPage = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $editPage->assertOk()->assertSee('Previous CS cannot be Yes when Para is 0.');
});
