<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3B.1 - Appointment Foundation (schema + model + relationships)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the appointments table
| contract, model vocabulary, relationships, patient-data resolution, the
| delivery-capacity ABSENCE guarantees, referential history protection and
| migration rollback safety - plus a Patient Portal regression smoke.
*/

function phase3b1Patient(string $first = 'Amara'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => 'Dela Cruz',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first) . '@phase3b1.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase3b1Appointment(Patient $patient, array $overrides = []): Appointment
{
    // Phase 3D.4B (P3): fixtures bypass the narrowed $fillable contract
    // on purpose - they seed every column (ownership, source, status)
    // that production code assigns explicitly in the service.
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ], $overrides));
}

/** Require the migration file fresh so up()/down() run against the test DB. */
function phase3b1Migration(): object
{
    return require database_path('migrations/2026_10_10_000001_create_appointments_table.php');
}

/*
|--------------------------------------------------------------------------
| 1-2. Schema contract
|--------------------------------------------------------------------------
*/

it('creates the appointments table in the test database', function () {
    expect(Schema::hasTable('appointments'))->toBeTrue();
});

it('has every required column with the approved nullability', function () {
    $columns = collect(Schema::getColumns('appointments'))->keyBy('name');

    foreach ([
        'id', 'patient_id', 'appointment_type', 'source', 'requested_date',
        'requested_time', 'confirmed_date', 'confirmed_time', 'status',
        'reviewed_by', 'reviewed_at', 'decline_reason', 'cancellation_reason',
        'created_at', 'updated_at', 'deleted_at',
    ] as $column) {
        expect($columns->has($column), "missing column {$column}")->toBeTrue();
    }

    // Required: ownership, type, source, requested schedule and status.
    foreach (['patient_id', 'appointment_type', 'source', 'requested_date', 'status'] as $column) {
        expect((bool) $columns[$column]['nullable'], "{$column} must be NOT NULL")->toBeFalse();
    }

    // Nullable: delivery requests need no time; confirmation is optional.
    foreach ([
        'requested_time', 'confirmed_date', 'confirmed_time',
        'reviewed_by', 'reviewed_at', 'decline_reason', 'cancellation_reason',
    ] as $column) {
        expect((bool) $columns[$column]['nullable'], "{$column} must be nullable")->toBeTrue();
    }

    // patient_id references patients.id (ON DELETE RESTRICT is proven
    // behaviorally below; introspection here only proves the FK exists).
    $patientFk = collect(Schema::getForeignKeys('appointments'))
        ->first(fn (array $fk): bool => $fk['columns'] === ['patient_id']);

    expect($patientFk)->not->toBeNull()
        ->and(strtolower((string) $patientFk['foreign_table']))->toBe('patients');
});

/*
|--------------------------------------------------------------------------
| 3-5. Relationships / ownership
|--------------------------------------------------------------------------
*/

it('requires the appointment to belong to an existing patient', function () {
    $patient = phase3b1Patient();
    $appointment = phase3b1Appointment($patient);

    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->patient)->toBeInstanceOf(Patient::class)
        ->and($appointment->patient->is($patient))->toBeTrue();

    // A non-existent patient id is refused by the foreign key - an
    // appointment can never be orphaned from the patients table.
    expect(fn () => Appointment::forceCreate([
        'patient_id' => 999999,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_WALK_IN,
        'requested_date' => '2026-11-05',
    ]))->toThrow(QueryException::class);
});

it('exposes the appointments relationship on Patient', function () {
    $patient = phase3b1Patient();
    $appointment = phase3b1Appointment($patient);

    expect($patient->appointments())->toBeInstanceOf(HasMany::class)
        ->and($patient->appointments()->count())->toBe(1)
        ->and($patient->appointments->first()->is($appointment))->toBeTrue();
});

it('resolves the reviewing staff member through the reviewer relationship', function () {
    $patient = phase3b1Patient();
    $reviewer = User::factory()->create(['role' => 'staff']);

    $pending = phase3b1Appointment($patient);
    expect($pending->reviewed_by)->toBeNull()
        ->and($pending->reviewer)->toBeNull();

    $confirmed = phase3b1Appointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => now(),
    ]);

    expect($confirmed->reviewer)->toBeInstanceOf(User::class)
        ->and($confirmed->reviewer->is($reviewer))->toBeTrue()
        ->and($confirmed->reviewed_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 6-11. Representable appointment shapes
|--------------------------------------------------------------------------
*/

it('represents a checkup appointment', function () {
    $appointment = phase3b1Appointment(phase3b1Patient(), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
    ]);

    expect($appointment->appointment_type)->toBe(Appointment::TYPE_CHECKUP)
        ->and(Appointment::TYPES)->toContain($appointment->appointment_type);
});

it('represents a counseling appointment', function () {
    $appointment = phase3b1Appointment(phase3b1Patient(), [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_time' => '14:00',
    ]);

    expect($appointment->appointment_type)->toBe(Appointment::TYPE_COUNSELING)
        ->and($appointment->fresh()->appointment_type)->toBe('counseling');
});

it('represents a delivery request without a required time', function () {
    $appointment = phase3b1Appointment(phase3b1Patient(), [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);

    expect($appointment->appointment_type)->toBe(Appointment::TYPE_DELIVERY_REQUEST)
        ->and($appointment->requested_time)->toBeNull()
        ->and($appointment->confirmed_date)->toBeNull()
        ->and($appointment->confirmed_time)->toBeNull();
});

it('represents both online and walk-in booking sources', function () {
    $patient = phase3b1Patient();

    $online = phase3b1Appointment($patient, ['source' => Appointment::SOURCE_ONLINE]);
    $walkIn = phase3b1Appointment($patient, ['source' => Appointment::SOURCE_WALK_IN]);

    expect($online->source)->toBe('online')
        ->and($walkIn->source)->toBe('walk_in')
        ->and(Appointment::SOURCES)->toBe(['online', 'walk_in']);
});

it('defaults to pending and can represent a confirmed status', function () {
    $patient = phase3b1Patient();

    $pending = phase3b1Appointment($patient);
    expect($pending->fresh()->status)->toBe('pending');

    $confirmed = phase3b1Appointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
    ]);
    expect($confirmed->fresh()->status)->toBe('confirmed');

    expect(Appointment::STATUSES)->toBe([
        'pending', 'confirmed', 'completed', 'cancelled', 'declined',
    ]);
});

it('keeps requested and confirmed schedules as distinct concepts', function () {
    $appointment = phase3b1Appointment(phase3b1Patient(), [
        'requested_date' => '2026-11-05',
        'requested_time' => '09:30',
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '14:15',
    ])->fresh();

    expect($appointment->requested_date->toDateString())->toBe('2026-11-05')
        ->and($appointment->requested_time)->toBe('09:30')
        ->and($appointment->confirmed_date->toDateString())->toBe('2026-11-06')
        ->and($appointment->confirmed_time)->toBe('14:15');

    // Still pending: confirming is an explicit act, never implied by the
    // presence of a confirmed schedule placeholder.
    expect($appointment->status)->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| 12-13. Shared dates and no patient-data duplication
|--------------------------------------------------------------------------
*/

it('allows multiple patients to hold appointments on the same date', function () {
    foreach (['Ana', 'Bianca', 'Cara'] as $name) {
        phase3b1Appointment(phase3b1Patient($name), [
            'requested_date' => '2026-12-01',
        ]);
    }

    expect(Appointment::whereDate('requested_date', '2026-12-01')->count())->toBe(3)
        ->and(Appointment::distinct()->count('patient_id'))->toBe(3);
});

it('stores no duplicated patient name or contact fields', function () {
    foreach (['first_name', 'last_name', 'middle_name', 'contact_number', 'email', 'address'] as $column) {
        expect(Schema::hasColumn('appointments', $column), "appointments.{$column} must not exist")->toBeFalse();
    }

    $appointment = phase3b1Appointment(phase3b1Patient());
    $attributeKeys = array_keys($appointment->getAttributes());

    foreach (['first_name', 'last_name', 'contact_number', 'email'] as $attribute) {
        expect($attributeKeys)->not->toContain($attribute);
    }

    // The data the future calendar needs comes from the relationship.
    expect($appointment->patient->first_name)->not->toBeEmpty()
        ->and($appointment->patient->contact_number)->not->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| 14-16. Delivery/clinical side effects deliberately ABSENT
|--------------------------------------------------------------------------
*/

it('does not automatically create prenatal visits', function () {
    $patient = phase3b1Patient();
    expect($patient->prenatalVisits()->count())->toBe(0);

    phase3b1Appointment($patient, ['appointment_type' => Appointment::TYPE_CHECKUP]);

    expect($patient->prenatalVisits()->count())->toBe(0)
        ->and(Patient::find($patient->id)->status)->toBe('ONGOING');
});

it('does not automatically create delivery admissions or outcome records', function () {
    $patient = phase3b1Patient();

    expect(Schema::hasTable('delivery_admissions'))->toBeFalse();

    phase3b1Appointment($patient, ['appointment_type' => Appointment::TYPE_DELIVERY_REQUEST]);

    expect(Schema::hasTable('delivery_admissions'))->toBeFalse()
        ->and(DB::table('pregnancy_outcomes')->count())->toBe(0)
        ->and(Patient::find($patient->id)->status)->toBe('ONGOING')
        ->and(Patient::find($patient->id)->delivery_date)->toBeNull();
});

it('contains no bed, occupancy or two-bed capacity logic', function () {
    $bedColumns = [
        'bed', 'bed_id', 'bed_number', 'occupancy', 'occupied',
        'admitted_at', 'discharged_at', 'admission_id',
    ];

    foreach ($bedColumns as $column) {
        expect(Schema::hasColumn('appointments', $column), "appointments.{$column} must not exist")->toBeFalse();
    }

    // Five same-day delivery requests all persist: the schema performs no
    // capacity check of any kind (the clinic's two-bed rule belongs to a
    // separate future occupancy entity, never to this table).
    foreach (['Ana', 'Bianca', 'Cara', 'Dina', 'Elsa'] as $name) {
        phase3b1Appointment(phase3b1Patient($name), [
            'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
            'requested_date' => '2027-01-10',
            'requested_time' => null,
        ]);
    }

    expect(Appointment::where('appointment_type', Appointment::TYPE_DELIVERY_REQUEST)->count())->toBe(5);
});

/*
|--------------------------------------------------------------------------
| 17-18. Existing records and Patient Portal unaffected
|--------------------------------------------------------------------------
*/

it('leaves existing patient records unaffected', function () {
    $patient = phase3b1Patient();
    $before = $patient->fresh()->toArray();

    phase3b1Appointment($patient);

    $after = Patient::find($patient->id)->toArray();

    expect($after['status'])->toBe($before['status'])
        ->and($after['first_name'])->toBe($before['first_name'])
        ->and($after['last_name'])->toBe($before['last_name'])
        ->and($after['contact_number'])->toBe($before['contact_number'])
        ->and($after['user_id'] ?? null)->toBe($before['user_id'] ?? null)
        ->and($after['deleted_at'] ?? null)->toBe($before['deleted_at'] ?? null);
});

it('keeps Patient Portal authentication behavior unaffected', function () {
    $user = User::factory()->create(['role' => 'patient']);
    $patient = phase3b1Patient();
    $patient->user_id = $user->id;
    $patient->save();

    // An appointment existing for the patient changes nothing about portal access.
    phase3b1Appointment($patient);

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('patient.profile.show'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Referential history policy (RESTRICT, never silent destruction)
|--------------------------------------------------------------------------
*/

it('keeps appointment history when a patient is soft-deleted', function () {
    $patient = phase3b1Patient();
    $appointment = phase3b1Appointment($patient);

    $patient->delete();

    expect(Patient::withTrashed()->find($patient->id))->not->toBeNull()
        ->and(Appointment::count())->toBe(1)
        ->and($appointment->fresh()->patient_id)->toBe($patient->id);
});

it('refuses to hard-delete a patient while appointment history exists', function () {
    $patient = phase3b1Patient();
    phase3b1Appointment($patient);

    expect(fn () => $patient->forceDelete())->toThrow(QueryException::class);

    // Nothing was destroyed: the patient row and the appointment both remain.
    expect(Patient::withTrashed()->find($patient->id))->not->toBeNull()
        ->and(Appointment::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Migration rollback safety (mirrors the pregnancy_outcomes guard)
|--------------------------------------------------------------------------
*/

it('rolls back an empty appointments table and can recreate it', function () {
    $migration = phase3b1Migration();

    $migration->down();
    expect(Schema::hasTable('appointments'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('appointments'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'confirmed_date'))->toBeTrue();
});

it('refuses to roll back a populated appointments table and keeps every row', function () {
    $appointment = phase3b1Appointment(phase3b1Patient());

    $migration = phase3b1Migration();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(DB::table('appointments')->count())->toBe(1)
        ->and(DB::table('appointments')->first()->id)->toBe($appointment->id)
        ->and(Schema::hasColumn('appointments', 'appointment_type'))->toBeTrue()
        ->and(Schema::hasColumn('appointments', 'cancellation_reason'))->toBeTrue();
});
