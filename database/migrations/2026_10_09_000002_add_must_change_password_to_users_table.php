<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 2B — first-login password-change flag on users.
 *
 * Additive, single-column change:
 *
 *   - `must_change_password` boolean, NOT NULL, default false.
 *   - Existing Admin/Staff accounts keep defaulting to false; no password is
 *     rewritten, no password reset is triggered and `email_verified_at` is
 *     untouched.
 *   - Phase 2B sets the flag to true explicitly (and non-mass-assignably) at
 *     the moment it provisions a patient login account. Enforcement of the
 *     flag is deliberately NOT implemented here or anywhere else yet — that is
 *     Phase 2C.
 *
 * Ownership safety (same invariant as 2026_10_09_000001, Phase 2A.1):
 *
 *   - `up()` refuses to run when the column already exists, BEFORE any DDL,
 *     because a pre-existing column was not created by this migration.
 *   - Laravel's Migrator writes the migration row only AFTER `up()` returns
 *     (Migrator::runUp() calls runMigration() and then repository->log()), and
 *     `down()` is only ever invoked for rows already recorded in `migrations`.
 *     Therefore `down()` can only run for a column this migration itself
 *     created, and dropping it here cannot take ownership of someone else's
 *     column. No ownership table, marker or configuration flag is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            throw new RuntimeException(
                'The users.must_change_password column already exists, but this migration '
                . 'did not create it, so it will not take ownership of it. No schema changes '
                . 'were made. Drop the existing column so this migration can own it, or remove '
                . 'this migration from the run.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')
                ->default(false)
                ->after('email_verified_at');
        });
    }

    /**
     * Remove ONLY the column this migration introduced.
     *
     * The guard keeps `down()` idempotent and mirrors the existing profile
     * fields migration (2026_10_05_000002). It never alters or drops any other
     * users column, index or constraint, and never touches stored passwords.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
