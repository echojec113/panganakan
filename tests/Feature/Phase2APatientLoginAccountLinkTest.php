<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 2A: the patients.user_id login-account link.
 *
 * These tests only exercise schema behavior and the two new model relations.
 * They deliberately contain no account-creation workflow, no login flow and
 * no patient-profile UI work - that is Phase 2B.
 */
function phase2aPatient(string $firstName = 'Ana'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Link',
        'age' => 25,
        'address' => 'Phase 2A test address',
        'contact_number' => '09171234567',
        'email' => strtolower($firstName) . '@phase2a.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase2aUser(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase2aMigration(): object
{
    return require database_path('migrations/2026_10_09_000001_add_user_id_to_patients_table.php');
}

/**
 * Deterministic snapshot of the patients table structure and rows, used to
 * prove that a refused up() wrote nothing at all.
 */
function phase2aPatientsSchemaSnapshot(): array
{
    $encode = fn ($value): string => json_encode($value, JSON_UNESCAPED_SLASHES);

    return [
        'columns' => collect(Schema::getColumns('patients'))
            ->map(fn (array $column): string => $encode([
                $column['name'],
                $column['type'],
                (bool) $column['nullable'],
                $column['default'],
            ]))
            ->sort()
            ->values()
            ->all(),
        'indexes' => collect(Schema::getIndexes('patients'))
            ->map(fn (array $index): string => $encode([
                $index['name'],
                $index['columns'],
                (bool) $index['unique'],
                (bool) $index['primary'],
            ]))
            ->sort()
            ->values()
            ->all(),
        'foreignKeys' => collect(Schema::getForeignKeys('patients'))
            ->map(fn (array $foreignKey): string => $encode([
                $foreignKey['columns'],
                $foreignKey['foreign_table'],
                $foreignKey['foreign_columns'],
                $foreignKey['on_delete'],
            ]))
            ->sort()
            ->values()
            ->all(),
        'rows' => DB::table('patients')
            ->orderBy('id')
            ->get()
            ->map(fn ($row): string => $encode((array) $row))
            ->all(),
    ];
}

/**
 * Run up() and return the refusal message, or null when it did not throw.
 */
function phase2aRefusalMessage(object $migration): ?string
{
    try {
        $migration->up();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
}

it('creates the patients.user_id column during a fresh migration run', function () {
    expect(Schema::hasColumn('patients', 'user_id'))->toBeTrue();
});

it('stores patients.user_id as a nullable column with a unique index and a set-null foreign key', function () {
    $column = collect(Schema::getColumns('patients'))
        ->firstWhere('name', 'user_id');

    expect($column)->not->toBeNull();
    expect((bool) $column['nullable'])->toBeTrue();

    $uniqueIndexes = collect(Schema::getIndexes('patients'))
        ->filter(fn (array $index): bool => $index['unique']
            && ! $index['primary']
            && $index['columns'] === ['user_id']);

    expect($uniqueIndexes)->toHaveCount(1);

    $foreignKey = collect(Schema::getForeignKeys('patients'))
        ->first(fn (array $candidate): bool => $candidate['columns'] === ['user_id']);

    expect($foreignKey)->not->toBeNull();
    expect(strtolower($foreignKey['foreign_table']))->toBe('users');
    expect($foreignKey['foreign_columns'])->toBe(['id']);
    expect(strtolower($foreignKey['on_delete']))->toBe('set null');
});

it('creates a patient that has no login account yet', function () {
    $patient = phase2aPatient();

    expect($patient->user_id)->toBeNull();
    expect(Patient::find($patient->id)->user_id)->toBeNull();
});

it('allows many patients to have no login account at the same time', function () {
    phase2aPatient('Ana');
    phase2aPatient('Bea');
    phase2aPatient('Cara');

    expect(
        DB::table('patients')->whereNull('user_id')->count()
    )->toBe(3);
});

it('links a patient to exactly one user account', function () {
    $user = phase2aUser();
    $patient = phase2aPatient();

    $patient->user_id = $user->id;
    $patient->save();

    $fresh = Patient::find($patient->id);

    expect($fresh->user_id)->toBe($user->id);
    expect($fresh->loginAccount())->toBeInstanceOf(BelongsTo::class);
    expect($fresh->loginAccount()->first()->id)->toBe($user->id);
    expect($fresh->loginAccount()->first()->role)->toBe('patient');
});

it('rejects the same user account being linked to a second patient', function () {
    $user = phase2aUser();

    $first = phase2aPatient('Ana');
    $first->user_id = $user->id;
    $first->save();

    $second = phase2aPatient('Bea');
    $second->user_id = $user->id;

    expect(fn () => $second->save())->toThrow(QueryException::class);

    expect(Patient::find($second->id)->user_id)->toBeNull();
    expect(Patient::find($first->id)->user_id)->toBe($user->id);
});

it('rejects a user_id value that does not exist in the users table', function () {
    $patient = phase2aPatient();
    $patient->user_id = (int) User::max('id') + 1000;

    expect(fn () => $patient->save())->toThrow(QueryException::class);

    expect(Patient::find($patient->id)->user_id)->toBeNull();
});

it('sets patients.user_id to null when the user account is hard-deleted, keeping the patient', function () {
    $user = phase2aUser();
    $patient = phase2aPatient();

    $patient->user_id = $user->id;
    $patient->save();

    $user->forceDelete();

    $survivor = Patient::withTrashed()->find($patient->id);

    expect($survivor)->not->toBeNull();
    expect($survivor->trashed())->toBeFalse();
    expect($survivor->user_id)->toBeNull();
    expect($survivor->first_name)->toBe('Ana');
});

it('keeps patients.user_id when the user account is only soft-deleted', function () {
    $user = phase2aUser();
    $patient = phase2aPatient();

    $patient->user_id = $user->id;
    $patient->save();

    $user->delete();

    expect($user->fresh()->deleted_at)->not->toBeNull();
    expect(Patient::find($patient->id)->user_id)->toBe($user->id);
});

it('resolves a soft-deleted login account through Patient::loginAccount', function () {
    $user = phase2aUser();
    $patient = phase2aPatient();

    $patient->user_id = $user->id;
    $patient->save();

    $user->delete();

    $login = Patient::find($patient->id)->loginAccount()->first();

    expect($login)->not->toBeNull();
    expect($login->id)->toBe($user->id);
    expect($login->trashed())->toBeTrue();
});

it('resolves the linked patient through User::patientAccount and excludes an archived patient', function () {
    $user = phase2aUser();
    $patient = phase2aPatient();

    $patient->user_id = $user->id;
    $patient->save();

    expect($user->patientAccount())->toBeInstanceOf(HasOne::class);
    expect($user->patientAccount()->first()->id)->toBe($patient->id);

    $patient->delete();

    expect($user->fresh()->patientAccount()->first())->toBeNull();
});

it('keeps assigned_staff_id independent of the login account link', function () {
    $staff = phase2aUser('staff');
    $login = phase2aUser('patient');

    $patient = phase2aPatient();
    $patient->assigned_staff_id = $staff->id;
    $patient->user_id = $login->id;
    $patient->save();

    $fresh = Patient::find($patient->id);

    expect($fresh->assigned_staff_id)->toBe($staff->id);
    expect($fresh->user_id)->toBe($login->id);
    expect($fresh->assignedStaff()->first()->id)->toBe($staff->id);
    expect($fresh->loginAccount()->first()->id)->toBe($login->id);

    $login->forceDelete();

    $fresh = Patient::find($patient->id);

    expect($fresh->user_id)->toBeNull();
    expect($fresh->assigned_staff_id)->toBe($staff->id);
    expect($fresh->assignedStaff()->first()->id)->toBe($staff->id);
});

it('does not allow patients.user_id to be set through mass assignment', function () {
    $user = phase2aUser();

    $patient = Patient::create([
        'first_name' => 'Ana',
        'last_name' => 'Link',
        'age' => 25,
        'address' => 'Phase 2A test address',
        'contact_number' => '09171234567',
        'email' => 'massassign@phase2a.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'user_id' => $user->id,
    ]);

    expect($patient->user_id)->toBeNull();
    expect(Patient::find($patient->id)->user_id)->toBeNull();
});

it('refuses a compatible pre-existing patients.user_id without touching the schema', function () {
    $staff = phase2aUser('staff');
    $login = phase2aUser('patient');

    // A fully linked patient, so the snapshot also covers live data.
    $patient = phase2aPatient('Ana');
    $patient->assigned_staff_id = $staff->id;
    $patient->user_id = $login->id;
    $patient->save();

    $migration = phase2aMigration();
    $before = phase2aPatientsSchemaSnapshot();

    // The column was created by migrate:fresh, not by this call, so up() must
    // refuse instead of adopting it - otherwise a later down() could drop a
    // column, unique index and foreign key this migration never created.
    $message = phase2aRefusalMessage($migration);

    expect($message)->not->toBeNull();
    expect($message)->toContain('did not create it');
    expect($message)->toContain('No schema changes were made');

    // Compatible pre-existing schema and its data are left exactly as found.
    expect(phase2aPatientsSchemaSnapshot())->toBe($before);

    $fresh = Patient::find($patient->id);

    expect($fresh->user_id)->toBe($login->id);
    expect($fresh->assigned_staff_id)->toBe($staff->id);
    expect($fresh->loginAccount()->first()->id)->toBe($login->id);
});

it('refuses an incompatible pre-existing patients.user_id column', function () {
    $migration = phase2aMigration();

    $migration->down();
    expect(Schema::hasColumn('patients', 'user_id'))->toBeFalse();

    Schema::table('patients', function ($table) {
        $table->string('user_id', 50);
    });

    $before = phase2aPatientsSchemaSnapshot();

    $message = phase2aRefusalMessage($migration);

    expect($message)->not->toBeNull();
    expect($message)->toContain('user_id must be nullable');
    expect($message)->toContain('user_id must be an integer/BIGINT column');
    expect($message)->toContain('a unique index on user_id is missing');
    expect($message)->toContain('a foreign key on user_id is missing');
    expect($message)->toContain('No schema changes were made');

    // The incompatible column is still exactly as found: no replacement, no
    // added constraints, nothing dropped.
    expect(phase2aPatientsSchemaSnapshot())->toBe($before);
    expect(
        collect(Schema::getColumns('patients'))->firstWhere('name', 'user_id')['type']
    )->toContain('varchar');

    // Restore the Phase 2A schema for the rest of the test session.
    Schema::table('patients', function ($table) {
        $table->dropColumn('user_id');
    });
    $migration->up();

    expect(Schema::hasColumn('patients', 'user_id'))->toBeTrue();
});

it('rolls back only the objects Phase 2A introduced', function () {
    $staff = phase2aUser('staff');
    $login = phase2aUser('patient');

    $patient = phase2aPatient('Ana');
    $patient->assigned_staff_id = $staff->id;
    $patient->user_id = $login->id;
    $patient->save();

    $migration = phase2aMigration();
    $migration->down();

    // Only the Phase 2A objects are gone.
    expect(Schema::hasColumn('patients', 'user_id'))->toBeFalse();
    expect(
        collect(Schema::getIndexes('patients'))
            ->contains(fn (array $index): bool => $index['columns'] === ['user_id'])
    )->toBeFalse();
    expect(
        collect(Schema::getForeignKeys('patients'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id'])
    )->toBeFalse();

    // Everything the migration did not create is untouched.
    expect(Schema::hasColumn('patients', 'assigned_staff_id'))->toBeTrue();
    expect(Schema::hasColumn('patients', 'first_name'))->toBeTrue();
    expect(Schema::hasColumn('patients', 'email'))->toBeTrue();
    expect(Schema::hasColumn('patients', 'status'))->toBeTrue();
    expect(
        collect(Schema::getForeignKeys('patients'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['assigned_staff_id'])
    )->toBeTrue();

    $row = DB::table('patients')->where('id', $patient->id)->first();

    expect($row)->not->toBeNull();
    expect($row->first_name)->toBe('Ana');
    expect($row->assigned_staff_id)->toBe($staff->id);

    // Existing patient records survive the rollback as queryable rows.
    expect(DB::table('patients')->count())->toBe(1);

    $survivor = Patient::find($patient->id);

    expect($survivor)->not->toBeNull();
    expect($survivor->trashed())->toBeFalse();
    expect($survivor->first_name)->toBe('Ana');
    expect($survivor->assigned_staff_id)->toBe($staff->id);
    expect($survivor->assignedStaff()->first()->id)->toBe($staff->id);

    // Restore the Phase 2A schema for the rest of the test session.
    $migration->up();

    expect(Schema::hasColumn('patients', 'user_id'))->toBeTrue();
});

it('cannot be enforced by the schema that a login account has role patient', function () {
    $admin = phase2aUser('admin');
    $patient = phase2aPatient();

    $patient->user_id = $admin->id;
    $patient->save();

    // The database accepts a non-patient role on purpose: users.role is a
    // plain string with a 'staff' default, so role-correct account creation
    // is a workflow concern for Phase 2B, not something a constraint can
    // guarantee. This test documents that limitation instead of hiding it.
    expect(Patient::find($patient->id)->user_id)->toBe($admin->id);
    expect(Patient::find($patient->id)->loginAccount()->first()->role)->toBe('admin');
});
