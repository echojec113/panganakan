<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function weightStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function weightPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Weight',
        'last_name' => 'Test',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ]);
}

function weightPayload(array $overrides = []): array
{
    return array_merge([
        'patient_id' => null,
        'visit_date' => now()->toDateString(),
        'bp_sys' => '110',
        'bp_dia' => '70',
        'weight' => '60',
        'gestational_age' => '20',
        'hypertension' => '0',
        'diabetes' => '0',
        'anemia' => '0',
    ], $overrides);
}

test('weight accepts whole number 30', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '30',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 30.5', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '30.5',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 60', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '60',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 41.2', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '41.2',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 60.9', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '60.9',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 249.9', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '249.9',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight accepts 250', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '250',
    ]))->assertSessionDoesntHaveErrors(['weight']);
});

test('weight rejects 29.9', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '29.9',
    ]))->assertSessionHasErrors(['weight']);
});

test('weight rejects 250.1', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '250.1',
    ]))->assertSessionHasErrors(['weight']);
});

test('weight rejects 60.55', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '60.55',
    ]))->assertSessionHasErrors(['weight']);
});

test('weight rejects -1', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '-1',
    ]))->assertSessionHasErrors(['weight']);
});

test('weight rejects abc', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => 'abc',
    ]))->assertSessionHasErrors(['weight']);
});

test('one-decimal weight is stored and retrieved correctly', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '60.5',
    ]));

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit->weight)->toBe(60.5);
    expect((float) DB::table('prenatal_visits')->where('id', $visit->id)->value('weight'))->toBe(60.5);
});

test('whole and one-decimal weights are stored with one-decimal precision', function () {
    $user = weightStaff();
    $patient = weightPatient();

    foreach (['60' => 60.0, '60.5' => 60.5, '60.9' => 60.9] as $input => $expected) {
        $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
            'patient_id' => $patient->id,
            'weight' => $input,
        ]))->assertSessionDoesntHaveErrors(['weight']);

        $visit = PrenatalVisit::where('patient_id', $patient->id)->orderByDesc('id')->first();
        expect($visit->weight)->toBe($expected);
        expect((float) DB::table('prenatal_visits')->where('id', $visit->id)->value('weight'))->toBe($expected);
    }
});

test('validation redirect preserves the submitted one-decimal weight', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)
        ->from(route('prenatal-visits.create'))
        ->post(route('prenatal-visits.store'), weightPayload([
            'patient_id' => $patient->id,
            'weight' => '60.5',
            'bp_sys' => '60',
            'bp_dia' => '70',
        ]))
        ->assertRedirect(route('prenatal-visits.create'))
        ->assertSessionHas('_old_input.weight', '60.5');
});

test('create and edit forms use one-decimal weight inputs and edit formats stored values', function () {
    $user = weightStaff();
    $patient = weightPatient();
    $visit = PrenatalVisit::create([
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
    $create->assertOk()->assertSee('step="0.1" min="30" max="250"', false);

    $edit = $this->actingAs($user)->get(route('prenatal-visits.edit', $visit->id));
    $edit->assertOk()
        ->assertSee('value="60.0"', false)
        ->assertSee('step="0.1" min="30" max="250"', false);
});

test('weight update accepts one-decimal values up to 250', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $visit = PrenatalVisit::create([
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

    $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '249.9',
    ]))->assertSessionDoesntHaveErrors(['weight']);

    $visit->refresh();
    expect($visit->weight)->toBe(249.9);
});

test('weight update rejects values above 250', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $visit = PrenatalVisit::create([
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

    $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '250.1',
    ]))->assertSessionHasErrors(['weight']);
});

test('one-decimal weight flows to risk assessment correctly', function () {
    $user = weightStaff();
    $patient = weightPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), weightPayload([
        'patient_id' => $patient->id,
        'weight' => '60.5',
    ]));

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit->weight)->toBe(60.5);
    expect($visit->risk_level)->not->toBeNull();
});
