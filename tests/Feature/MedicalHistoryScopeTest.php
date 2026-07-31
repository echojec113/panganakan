<?php

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\CompletenessValidator;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Pre-existing documented gap: patients migration files are missing the
    // previous_cs/miscarriage columns. Add them to the in-memory test schema
    // only, guarded so the tests keep working once real migrations add them.
    if (!Schema::hasColumn('patients', 'previous_cs')) {
        Schema::table('patients', function ($table) {
            $table->boolean('previous_cs')->default(false);
        });
    }

    if (!Schema::hasColumn('patients', 'miscarriage')) {
        Schema::table('patients', function ($table) {
            $table->integer('miscarriage')->default(0);
        });
    }
});

function mhStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function mhPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Med',
        'last_name' => 'History',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function mhPayload(array $overrides = []): array
{
    return array_merge([
        'patient_id' => null,
    ], $overrides);
}

it('stores a medical history with booleans normalized and other_specify null when other is unchecked', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $response = $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
        'diabetes' => '1',
        'anemia' => '0',
    ]));

    $response->assertRedirect(route('patients.show', $patient->id));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history)->not->toBeNull();
    expect($history->diabetes)->toBe(1);
    expect($history->anemia)->toBe(0);
    expect($history->severe_headache)->toBe(0);
    expect($history->other_specify)->toBeNull();
});

it('rejects a medical history for a patient id that does not exist', function () {
    $user = mhStaff();

    $response = $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => 999999,
    ]));

    $response->assertSessionHasErrors('patient_id');
});

it('requires other_specify when the other checkbox is checked', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $response = $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
        'other' => '1',
    ]));

    $response->assertSessionHasErrors('other_specify');
});

it('saves other_specify when the other checkbox is checked', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
        'other' => '1',
        'other_specify' => 'Varicose veins',
    ]));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->other_specify)->toBe('Varicose veins');
});

it('stores every unchecked field as false', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
    ]));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history)->not->toBeNull();
    expect($history->epilepsy)->toBe(0);
    expect($history->diabetes)->toBe(0);
    expect($history->anemia)->toBe(0);
    expect($history->severe_headache)->toBe(0);
    expect($history->other_specify)->toBeNull();
});

it('preserves the original patient_id on update even if a different id is posted', function () {
    $user = mhStaff();
    $patient = mhPatient();
    $otherPatient = mhPatient();

    $history = MedicalHistory::create(['patient_id' => $patient->id]);

    $this->actingAs($user)->put(route('medical-histories.update', $history->id), mhPayload([
        'patient_id' => $otherPatient->id,
        'diabetes' => '1',
    ]));

    $history->refresh();

    expect($history->patient_id)->toBe($patient->id);
    expect($history->diabetes)->toBe(1);
});

it('clears other_specify on update when the other checkbox is unchecked', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $history = MedicalHistory::create([
        'patient_id' => $patient->id,
        'other_specify' => 'Varicose veins',
    ]);

    $this->actingAs($user)->put(route('medical-histories.update', $history->id), mhPayload([]));

    $history->refresh();

    expect($history->other_specify)->toBeNull();
});

it('requires other_specify on update when the other checkbox is checked', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $history = MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->put(route('medical-histories.update', $history->id), mhPayload([
        'other' => '1',
    ]));

    $response->assertSessionHasErrors('other_specify');
});

it('redirects create to edit when a medical history record already exists', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $existing = MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->get(route('medical-histories.create', ['patient_id' => $patient->id]));

    $response->assertRedirect(route('medical-histories.edit', $existing->id));
    $response->assertSessionHas('info');
    $response->assertSessionHas('info', 'A Medical History record already exists for this pregnancy.');
});

it('redirects store to edit and creates no second record when one already exists', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $existing = MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
        'diabetes' => '1',
    ]));

    $response->assertRedirect(route('medical-histories.edit', $existing->id));
    $response->assertSessionHas('info', 'A Medical History record already exists for this pregnancy.');

    expect(MedicalHistory::where('patient_id', $patient->id)->count())->toBe(1);
});

it('blocks create for a delivered patient', function () {
    $user = mhStaff();
    $patient = mhPatient(['status' => 'DELIVERED']);

    $response = $this->actingAs($user)->get(route('medical-histories.create', ['patient_id' => $patient->id]));

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('error');
});

it('blocks store for a delivered patient without creating a record', function () {
    $user = mhStaff();
    $patient = mhPatient(['status' => 'DELIVERED']);

    $response = $this->actingAs($user)->post(route('medical-histories.store'), mhPayload([
        'patient_id' => $patient->id,
        'diabetes' => '1',
    ]));

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('error');

    expect(MedicalHistory::where('patient_id', $patient->id)->count())->toBe(0);
});

it('blocks edit for a delivered patient', function () {
    $user = mhStaff();
    $patient = mhPatient(['status' => 'DELIVERED']);

    $history = MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->get(route('medical-histories.edit', $history->id));

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('error');
});

it('blocks update for a delivered patient without changing the record', function () {
    $user = mhStaff();
    $patient = mhPatient(['status' => 'DELIVERED']);

    $history = MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->put(route('medical-histories.update', $history->id), mhPayload([
        'diabetes' => '1',
    ]));

    $response->assertRedirect(route('patients.show', $patient->id));
    $response->assertSessionHas('error');

    $history->refresh();

    expect($history->diabetes)->toBe(0);
});

it('renders the create form with grouped sections, scope notes, and a stable form id', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $response = $this->actingAs($user)->get(route('medical-histories.create', ['patient_id' => $patient->id]));

    $response->assertOk();
    $response->assertSee('id="medical-history-form"', false);
    $response->assertSee('Conditions Also Assessed During Prenatal Visits');
    $response->assertSee('Confirmed during prenatal visits and updated in this background record.');
    $response->assertSee('Chronic & Background Conditions');
    $response->assertSee('Recorded for the health record only.');
    $response->assertSee('Legacy Historical or Recurring Concerns');
    $response->assertSee('These fields store previously reported or recurring concerns.');
    $response->assertSee('are also assessed during prenatal visits');
    $response->assertDontSee('CDSS-Active Factors');
    $response->assertDontSee('Warning Symptoms & Notes');
    $response->assertDontSee('affect the risk assessment.');
    $response->assertDontSee('never used in the risk assessment.');
});

it('renders the edit form preserving the current checked state and other_specify value', function () {
    $user = mhStaff();
    $patient = mhPatient();

    $history = MedicalHistory::create([
        'patient_id' => $patient->id,
        'diabetes' => true,
        'other_specify' => 'Varicose veins',
    ]);

    $response = $this->actingAs($user)->get(route('medical-histories.edit', $history->id));

    $response->assertOk();
    $response->assertSee('id="medical-history-form"', false);
    $response->assertSee('name="diabetes" value="1" checked', false);
    $response->assertSee('name="severe_headache" value="1"', false);
    $response->assertSee('Varicose veins');
});

it('groups the medical history on the patient profile with scope notes and other_specify', function () {
    $user = mhStaff();
    $patient = mhPatient();

    MedicalHistory::create([
        'patient_id' => $patient->id,
        'diabetes' => true,
        'severe_headache' => true,
        'other_specify' => 'Varicose veins',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Conditions Also Assessed During Prenatal Visits');
    $response->assertSee('Confirmed during prenatal visits and updated in this background record.');
    $response->assertSee('Legacy Historical or Recurring Concerns');
    $response->assertSee('Other: Varicose veins');
    $response->assertDontSee('CDSS-Active Factors');
    $response->assertDontSee('never used in the risk assessment.');
});

it('explains the one-way background sync on prenatal visit create and edit forms', function () {
    $user = mhStaff();
    $patient = mhPatient();

    MedicalHistory::create(['patient_id' => $patient->id]);

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
    ]);

    $create = $this->actingAs($user)->get(route('prenatal-visits.create'));
    $create->assertOk();
    $create->assertSee('When marked Yes, the existing Medical History background record will also be updated.');

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    $edit = $this->actingAs($user)->get(route('prenatal-visits.edit', $visit->id));
    $edit->assertOk();
    $edit->assertSee('When marked Yes, the existing Medical History background record will also be updated.');
});

it('treats medical history existence, not its content, as the completeness gate', function () {
    $patient = mhPatient();

    MedicalHistory::create([
        'patient_id' => $patient->id,
        'severe_headache' => true,
        'visual_disturbance' => true,
        'chest_pain' => true,
        'shortness_breath' => true,
    ]);

    \App\Models\Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);
    \App\Models\BirthPlan::create(['patient_id' => $patient->id]);

    $validator = new CompletenessValidator;

    expect($validator->missingRequiredRecords($patient))->toBe([]);
    expect($validator->isComplete($patient))->toBeTrue();
});
