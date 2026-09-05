<?php

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17B — data-foundation contract for the additive
 * pregnancy_outcomes migration.
 *
 * All assertions run against the in-memory SQLite test database; the
 * migration is exercised directly so up()/down() are proven on the real
 * schema definition.
 */

function pregnancyOutcomeMigrationInstance(): object
{
    return require database_path('migrations/2026_08_09_000002_create_pregnancy_outcomes_table.php');
}

function pregnancyOutcomeMigrationPatient(string $firstName = 'Outcome'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

it('creates the pregnancy_outcomes table with the expected data-foundation columns', function () {
    expect(Schema::hasTable('pregnancy_outcomes'))->toBeTrue();

    foreach ([
        'id',
        'patient_id',
        'outcome_type',
        'delivery_location',
        'follow_up_status',
        'follow_up_recorded_at',
        'follow_up_recorded_by',
        'confirmation_source',
        'confirmed_at',
        'confirmed_by',
        'notes',
        'created_at',
        'updated_at',
    ] as $column) {
        expect(Schema::hasColumn('pregnancy_outcomes', $column))->toBeTrue("missing column $column");
    }
});

it('does not persist the redundant outcome_confirmed or a duplicate delivery_date', function () {
    expect(Schema::hasColumn('pregnancy_outcomes', 'outcome_confirmed'))->toBeFalse();
    expect(Schema::hasColumn('pregnancy_outcomes', 'delivery_date'))->toBeFalse();
});

it('enforces exactly one outcome record per pregnancy via a UNIQUE patient_id', function () {
    $patient = pregnancyOutcomeMigrationPatient('Unique');

    PregnancyOutcome::create(['patient_id' => $patient->id, 'outcome_type' => 'DELIVERED']);

    expect(fn () => PregnancyOutcome::create(['patient_id' => $patient->id, 'outcome_type' => 'DELIVERED']))
        ->toThrow(QueryException::class);

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1);
});

it('nulls confirmation provenance when the recorder user is force-deleted', function () {
    $user = User::factory()->create();
    $patient = pregnancyOutcomeMigrationPatient('ProvenanceConfirm');

    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => 'DELIVERED',
        'delivery_location' => 'HOME',
        'confirmation_source' => 'PATIENT_REPORT',
        'confirmed_at' => now(),
        'confirmed_by' => $user->id,
    ]);

    $user->forceDelete();

    $outcome = PregnancyOutcome::where('patient_id', $patient->id)->first();
    expect($outcome->confirmed_by)->toBeNull();
    expect($outcome->outcome_type)->toBe('DELIVERED');
    expect($outcome->delivery_location)->toBe('HOME');
});

it('nulls the follow-up recorder when the user is force-deleted', function () {
    $user = User::factory()->create();
    $patient = pregnancyOutcomeMigrationPatient('FollowupProvenance');

    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => 'UNABLE_TO_CONTACT',
        'follow_up_recorded_at' => now(),
        'follow_up_recorded_by' => $user->id,
    ]);

    $user->forceDelete();

    $outcome = PregnancyOutcome::where('patient_id', $patient->id)->first();
    expect($outcome->follow_up_recorded_by)->toBeNull();
    expect($outcome->follow_up_status)->toBe('UNABLE_TO_CONTACT');
    expect($outcome->outcome_type)->toBeNull();
});

it('cascades the outcome record away when a patient is hard-deleted', function () {
    $patient = pregnancyOutcomeMigrationPatient('Cascade');

    PregnancyOutcome::create(['patient_id' => $patient->id, 'outcome_type' => 'DELIVERED']);

    $patient->forceDelete();

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(0);
});

it('A. rolls back an empty pregnancy_outcomes table (allowed)', function () {
    $migration = pregnancyOutcomeMigrationInstance();

    $migration->down();

    expect(Schema::hasTable('pregnancy_outcomes'))->toBeFalse();
});

it('B. refuses to roll back a populated pregnancy_outcomes table', function () {
    $migration = pregnancyOutcomeMigrationInstance();

    PregnancyOutcome::create(['patient_id' => pregnancyOutcomeMigrationPatient('Populated')->id, 'outcome_type' => 'DELIVERED']);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});

it('C. leaves the persisted row unchanged after a rejected rollback', function () {
    $migration = pregnancyOutcomeMigrationInstance();

    $patient = pregnancyOutcomeMigrationPatient('Immutable');
    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => 'DELIVERED',
        'delivery_location' => 'ANOTHER_FACILITY',
        'confirmation_source' => 'OTHER_FACILITY_REPORT',
        'notes' => 'Correction test — must survive a rejected rollback.',
    ]);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    $row = DB::table('pregnancy_outcomes')->where('patient_id', $patient->id)->first();
    expect($row)->not->toBeNull();
    expect($row->outcome_type)->toBe('DELIVERED');
    expect($row->delivery_location)->toBe('ANOTHER_FACILITY');
    expect($row->notes)->toBe('Correction test — must survive a rejected rollback.');
});

it('D. keeps the table and all outcome columns present after a rejected rollback', function () {
    $migration = pregnancyOutcomeMigrationInstance();

    PregnancyOutcome::create(['patient_id' => pregnancyOutcomeMigrationPatient('Columns')->id, 'outcome_type' => 'DELIVERED']);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Schema::hasTable('pregnancy_outcomes'))->toBeTrue();
    foreach ([
        'id',
        'patient_id',
        'outcome_type',
        'delivery_location',
        'follow_up_status',
        'follow_up_recorded_at',
        'follow_up_recorded_by',
        'confirmation_source',
        'confirmed_at',
        'confirmed_by',
        'notes',
    ] as $column) {
        expect(Schema::hasColumn('pregnancy_outcomes', $column))->toBeTrue("missing column $column");
    }
});

it('E. can run up() again after a successful empty rollback', function () {
    $migration = pregnancyOutcomeMigrationInstance();

    $migration->down();
    expect(Schema::hasTable('pregnancy_outcomes'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('pregnancy_outcomes'))->toBeTrue();
    expect(Schema::hasColumn('pregnancy_outcomes', 'patient_id'))->toBeTrue();
});