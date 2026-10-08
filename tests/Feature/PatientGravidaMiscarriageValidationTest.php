<?php

use App\Models\Patient;
use App\Models\User;

/**
 * Gravida 0 support + Miscarriage conditional validation.
 *
 * Rules under test:
 * - Gravida required, whole number >= 0 (no negatives, decimals, blanks).
 * - Gravida 0 => Miscarriage must be 0.
 * - Gravida 1 + ONGOING => Miscarriage must be 0 (new records default ONGOING).
 * - Gravida 1 on non-ONGOING (historical) records => Miscarriage 1 allowed.
 * - Gravida >= 2 => Miscarriage 0 or 1 allowed.
 * - Previous CS validation from the prior sprint must keep working.
 */

function gravidaMiscStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function gravidaMiscStorePayload(array $overrides = []): array
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
        'gravida' => 0,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
    ], $overrides);
}

function gravidaMiscPatientFixture(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Legacy',
        'last_name' => 'Patient',
        'birthdate' => today()->subYears(28)->toDateString(),
        'age' => 28,
        'address' => 'Santa Maria',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
        'status' => 'ONGOING',
    ], $overrides));
}

function gravidaMiscUpdatePayload(Patient $patient, array $overrides = []): array
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
        'para' => $patient->para,
        'previous_cs' => (string) $patient->previous_cs,
        'miscarriage' => $patient->miscarriage,
        'lmp' => $patient->lmp->format('Y-m-d'),
        'edd' => $patient->edd->format('Y-m-d'),
    ], $overrides);
}

// ---------------------------------------------------------------------------
// Gravida / Miscarriage acceptance matrix (store)
// ---------------------------------------------------------------------------

it('accepts gravida 0 with miscarriage 0 on create', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 0, 'para' => 0, 'miscarriage' => 0])
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect((int) $patient->gravida)->toBe(0)
        ->and((int) $patient->miscarriage)->toBe(0);
});

it('rejects gravida 0 with miscarriage 1 on create', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 0, 'para' => 0, 'miscarriage' => 1])
    );

    $response->assertSessionHasErrors([
        'miscarriage' => 'Miscarriage cannot be Yes when Gravida is 0.',
    ]);

    expect(Patient::count())->toBe(0);
});

it('accepts gravida 1 with miscarriage 0 on create', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 1, 'para' => 0, 'miscarriage' => 0])
    );

    $response->assertRedirect(route('patients.index'));

    expect((int) Patient::latest('id')->first()->gravida)->toBe(1);
});

it('rejects gravida 1 with miscarriage 1 on create because new records are ongoing', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 1, 'para' => 0, 'miscarriage' => 1])
    );

    $response->assertSessionHasErrors([
        'miscarriage' => 'Miscarriage cannot be Yes when Gravida is 1 for an ongoing pregnancy.',
    ]);

    expect(Patient::count())->toBe(0);
});

it('accepts gravida 2 with miscarriage 1 on create', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 2, 'para' => 1, 'miscarriage' => 1])
    );

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect((int) $patient->gravida)->toBe(2)
        ->and((int) $patient->miscarriage)->toBe(1);
});

// ---------------------------------------------------------------------------
// Gravida input validation (store)
// ---------------------------------------------------------------------------

it('rejects a negative gravida', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => -1])
    );

    $response->assertSessionHasErrors('gravida');

    expect(Patient::count())->toBe(0);
});

it('rejects a decimal gravida', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => '1.5'])
    );

    $response->assertSessionHasErrors('gravida');

    expect(Patient::count())->toBe(0);
});

it('rejects a blank gravida', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => ''])
    );

    $response->assertSessionHasErrors('gravida');

    expect(Patient::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Update path
// ---------------------------------------------------------------------------

it('keeps the gravida 0 ongoing rule on update', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 0, 'para' => 0, 'miscarriage' => 0]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), gravidaMiscUpdatePayload($patient, [
            'gravida' => 0,
            'miscarriage' => 1,
        ]));

    $response->assertSessionHasErrors([
        'miscarriage' => 'Miscarriage cannot be Yes when Gravida is 0.',
    ]);

    expect((int) $patient->fresh()->miscarriage)->toBe(0);
});

it('rejects gravida 1 with miscarriage 1 on update for an ongoing first pregnancy', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 1, 'para' => 0, 'miscarriage' => 0, 'status' => 'ONGOING']);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), gravidaMiscUpdatePayload($patient, [
            'gravida' => 1,
            'miscarriage' => 1,
        ]));

    $response->assertSessionHasErrors([
        'miscarriage' => 'Miscarriage cannot be Yes when Gravida is 1 for an ongoing pregnancy.',
    ]);

    expect((int) $patient->fresh()->miscarriage)->toBe(0);
});

it('allows gravida 1 with miscarriage 1 on an existing delivered record', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture([
        'gravida' => 1,
        'para' => 1,
        'miscarriage' => 0,
        'status' => 'DELIVERED',
        'delivery_date' => today()->subMonth()->toDateString(),
    ]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), gravidaMiscUpdatePayload($patient, [
            'gravida' => 1,
            'para' => 1,
            'miscarriage' => 1,
        ]));

    $response->assertRedirect(route('patients.index'));

    expect((int) $patient->fresh()->miscarriage)->toBe(1);
});

it('keeps an existing gravida 0 record editable when otherwise valid', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 0, 'para' => 0, 'previous_cs' => 0, 'miscarriage' => 0]);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), gravidaMiscUpdatePayload($patient, [
            'gravida' => 0,
            'para' => 0,
            'miscarriage' => 0,
        ]));

    $response->assertRedirect(route('patients.index'));

    $patient->refresh();

    expect((int) $patient->gravida)->toBe(0)
        ->and((int) $patient->miscarriage)->toBe(0);
});

// ---------------------------------------------------------------------------
// Existing Previous CS validation must keep working
// ---------------------------------------------------------------------------

it('still rejects para 0 with previous cs 1 after the gravida changes', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->post(
        route('patients.store'),
        gravidaMiscStorePayload(['gravida' => 1, 'para' => 0, 'previous_cs' => '1', 'miscarriage' => 0])
    );

    $response->assertSessionHasErrors([
        'previous_cs' => 'Previous CS cannot be Yes when Para is 0.',
    ]);

    expect(Patient::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// UI wiring (server-rendered assertions; runtime JS verified manually)
// ---------------------------------------------------------------------------

it('renders gravida defaulted to 0 with min 0 on the create page', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->get(route('patients.create'));

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/<input(?=[^>]*id="gravida")(?=[^>]*value="0")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="gravida")(?=[^>]*min="0")[^>]*>/s');
});

it('renders the miscarriage dropdown with its conditional script on the create page', function () {
    $staff = gravidaMiscStaff();

    $response = $this->actingAs($staff)->get(route('patients.create'));

    expect($response->getContent())
        ->toContain('id="miscarriage"')
        ->toContain('syncMiscarriageToGravida')
        ->toContain('prepareMiscarriageForSubmit');
});

it('preserves the stored gravida and renders the status-aware miscarriage script on the edit page', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 0, 'para' => 0]);

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/<input(?=[^>]*id="gravida")(?=[^>]*value="0")[^>]*>/s')
        ->toContain('id="miscarriage"')
        ->toContain('syncMiscarriageToGravida')
        ->toContain('patientStatus = "ONGOING"');
});

it('does not overwrite an existing stored gravida when rendering the edit page', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 3, 'para' => 2]);

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    expect($response->getContent())
        ->toMatch('/<input(?=[^>]*id="gravida")(?=[^>]*value="3")[^>]*>/s');
});

it('wires gravida change events to the miscarriage sync so gravida changes reset the dropdown', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture();

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $content = $response->getContent();

    expect($content)
        ->toContain('gravidaInput.addEventListener("input", syncMiscarriageToGravida)')
        ->toContain('gravidaInput.addEventListener("change", syncMiscarriageToGravida)')
        ->toContain('syncMiscarriageToGravida();')
        ->toContain("miscarriageSelect.value = '0';")
        ->toContain('miscarriageSelect.disabled = true;');
});

it('surfaces the miscarriage validation message on the edit page after a rejected update', function () {
    $staff = gravidaMiscStaff();
    $patient = gravidaMiscPatientFixture(['gravida' => 1, 'para' => 0, 'miscarriage' => 0, 'status' => 'ONGOING']);

    $response = $this->actingAs($staff)
        ->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), gravidaMiscUpdatePayload($patient, [
            'gravida' => 1,
            'miscarriage' => 1,
        ]));

    $response->assertSessionHasErrors('miscarriage');

    $editPage = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $editPage->assertOk()->assertSee('Miscarriage cannot be Yes when Gravida is 1 for an ongoing pregnancy.');
});
