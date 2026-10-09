<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the DEPLA Phase 2A patient login-account link.
     *
     * patients.user_id is deliberately a different column from
     * patients.assigned_staff_id: assigned_staff_id records which clinic
     * staff member looks after the pregnancy and is a clinical assignment,
     * while user_id points at the account the patient signs in with. The two
     * must never be conflated.
     *
     * - nullable so existing patients keep working with no account yet
     * - unique so one user account can never belong to two patients
     * - ON DELETE SET NULL so removing a user account never removes a patient
     *
     * OWNERSHIP CONTRACT (Phase 2A.1)
     * -------------------------------
     * up() creates the column only when patients.user_id is ABSENT, and throws
     * when it is already there - even when the existing column happens to
     * match the Phase 2A shape. Nothing is written before that throw.
     *
     * Laravel writes a migration row only AFTER up() returns
     * (Migrator::runUp: runMigration() then repository->log()), and a
     * rollback only ever reaches this file for a migration that has a row
     * (Migrator::runDown is fed from the repository). So a recorded migration
     * is proof that this migration created the column itself.
     *
     * down() runs in a separate process with no memory of up(), and there is
     * no schema attribute that distinguishes "created by up()" from "created
     * by something else". Rather than guess - and risk dropping a column,
     * unique index and foreign key this migration does not own - ownership is
     * settled once, in up(), by refusing to adopt anything it did not create.
     * The rollback below is therefore safe by construction, not by detection,
     * and needs no ownership table, marker or configuration flag.
     */
    public function up(): void
    {
        if (Schema::hasColumn('patients', 'user_id')) {
            throw $this->preExistingColumnError();
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->after('assigned_staff_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migration.
     *
     * Drops exactly the three objects this migration creates - the foreign
     * key, the unique index and the column - and nothing else.
     *
     * Safe because up() refuses every pre-existing patients.user_id: if this
     * migration has a row in the migrations table, up() ran to completion
     * against an absent column, so everything found here was created here.
     * When up() was refused the migration is never recorded and the migrator
     * never calls down().
     */
    public function down(): void
    {
        if (! Schema::hasColumn('patients', 'user_id')) {
            return;
        }

        $foreignKey = $this->foreignKeyOnUserId();
        $uniqueIndex = $this->uniqueIndexOnUserId();

        Schema::table('patients', function (Blueprint $table) use ($foreignKey) {
            $table->dropForeign($foreignKey);
        });

        Schema::table('patients', function (Blueprint $table) use ($uniqueIndex) {
            $table->dropUnique($uniqueIndex);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }

    /**
     * Build the refusal for a pre-existing patients.user_id.
     *
     * Read-only: it inspects the schema to explain why the column cannot be
     * adopted and never writes anything. A matching column is refused for a
     * different reason than a mismatching one - ownership vs. shape - so the
     * two get distinct, actionable messages.
     */
    private function preExistingColumnError(): RuntimeException
    {
        $problems = $this->shapeProblems();

        if ($problems === []) {
            return new RuntimeException(
                'DEPLA Phase 2A migration refused: patients.user_id already exists and '
                . 'matches the expected shape, but this migration did not create it, so it '
                . 'will not take ownership of it. A migration row is only written after '
                . 'up() returns; adopting a column created elsewhere would let a later '
                . '"php artisan migrate:rollback" drop a column, unique index and foreign '
                . 'key that belong to something else. No schema changes were made. '
                . 'To let this migration own the column, drop the existing patients.user_id '
                . '(with its unique index and foreign key to users.id) and run it again; '
                . 'to keep the existing column, remove this migration from the run.'
            );
        }

        return new RuntimeException(
            'DEPLA Phase 2A migration refused: patients.user_id already exists but is not '
            . 'the Phase 2A login-account link (' . implode('; ', $problems) . '). '
            . 'No schema changes were made and the existing column was left untouched. '
            . 'Resolve patients.user_id manually before running this migration again.'
        );
    }

    /**
     * Describe every way a pre-existing patients.user_id differs from the
     * column this migration is meant to create. An empty array means the
     * column is shape-compatible - which still does not make it ours.
     *
     * @return array<int, string>
     */
    private function shapeProblems(): array
    {
        $column = collect(Schema::getColumns('patients'))
            ->firstWhere('name', 'user_id');

        if ($column === null) {
            return ['patients.user_id is reported present but could not be introspected'];
        }

        $problems = [];

        if (! (bool) $column['nullable']) {
            $problems[] = 'user_id must be nullable';
        }

        $type = strtolower((string) $column['type']);

        if (! str_contains($type, 'int')) {
            $problems[] = 'user_id must be an integer/BIGINT column (found "' . $column['type'] . '")';
        }

        $hasUniqueIndex = collect(Schema::getIndexes('patients'))
            ->contains(fn (array $index): bool => $index['unique']
                && ! $index['primary']
                && $index['columns'] === ['user_id']);

        if (! $hasUniqueIndex) {
            $problems[] = 'a unique index on user_id is missing';
        }

        $foreignKey = collect(Schema::getForeignKeys('patients'))
            ->first(fn (array $candidate): bool => $candidate['columns'] === ['user_id']);

        if ($foreignKey === null) {
            $problems[] = 'a foreign key on user_id is missing';
        } elseif (strtolower((string) $foreignKey['foreign_table']) !== 'users'
            || $foreignKey['foreign_columns'] !== ['id']
            || strtolower((string) $foreignKey['on_delete']) !== 'set null') {
            $problems[] = 'the foreign key must reference users.id with on delete set null (found '
                . $foreignKey['foreign_table'] . '(' . implode(',', $foreignKey['foreign_columns']) . ') '
                . 'on delete ' . $foreignKey['on_delete'] . ')';
        }

        return $problems;
    }

    /**
     * Resolve the foreign key that owns patients.user_id.
     *
     * MySQL reports the real constraint name from information_schema, so the
     * drop targets the actual constraint. SQLite's schema introspection does
     * not expose constraint names, so the conventional Laravel name is used -
     * SQLite compiles the drop from the column list anyway, and the name only
     * decides which foreign keys are removed from the rebuilt table.
     *
     * @return array<int, int>|array<int, string>|string
     */
    private function foreignKeyOnUserId(): array|string
    {
        $name = collect(Schema::getForeignKeys('patients'))
            ->first(fn (array $foreignKey): bool => $foreignKey['columns'] === ['user_id'])['name'] ?? null;

        return $name ?? ['user_id'];
    }

    /**
     * Resolve the unique index on patients.user_id by its real name so the
     * exact index this migration created is the one that is dropped.
     */
    private function uniqueIndexOnUserId(): array|string
    {
        $name = collect(Schema::getIndexes('patients'))
            ->first(fn (array $index): bool => $index['unique']
                && ! $index['primary']
                && $index['columns'] === ['user_id'])['name'] ?? null;

        return $name ?? ['user_id'];
    }
};
