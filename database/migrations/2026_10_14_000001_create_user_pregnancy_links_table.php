<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPLA Phase 3H.2 - verified user-to-pregnancy relationship table.
     *
     * ARCHITECTURE (Option B, user-approved in Phase 3H.1)
     * ----------------------------------------------------
     * users            = login account
     * patients         = one row per pregnancy episode
     * patients.user_id = UNIQUE current-pregnancy pointer (Phase 2A,
     *                    untouched by this migration - it remains the ONLY
     *                    selector of which pregnancy a portal session sees)
     * user_pregnancy_links (this table) = durable verified OWNERSHIP
     *                    evidence: every pregnancy episode an account has
     *                    been explicitly linked to, past or present.
     *
     * The link table does NOT select the current pregnancy, does NOT grant
     * Patient Portal route access by itself, and introduces NO is_current
     * column or second pointer. Existing portal controllers keep resolving
     * through User::patientAccount() (patients.user_id). One account may
     * hold many verified links; only one patients row may hold the pointer
     * at a time (Phase 2A unique constraint).
     *
     * OWNERSHIP AND VERIFICATION CONTRACT
     * -----------------------------------
     * - Rows are created ONLY from an explicit, authorized pairing: the
     *   Phase 2B account-creation transaction (PatientAccountCreationService)
     *   writes one row alongside patients.user_id, or a future approved
     *   verification workflow does the same. NEVER from name, birthdate,
     *   email, contact-number, address or gravida/para matching, and never
     *   by "selecting the latest pregnancy".
     * - verified_by is the acting staff/admin when reliably available, else
     *   NULL. verified_at is when the pairing was recorded. Both are
     *   provenance, not access-control state.
     * - The UNIQUE (user_id, patient_id) pair makes the relationship
     *   idempotent: a retried or repeated verification can never stack
     *   duplicate evidence rows for the same pairing.
     *
     * REFERENTIAL POLICY
     * ------------------
     * - user_id    -> RESTRICT: verified ownership history must never be
     *                 silently destroyed by deleting a login account.
     * - patient_id -> RESTRICT: same reason for pregnancy episodes.
     * - verified_by -> SET NULL: verifier provenance follows the existing
     *                 reviewer-provenance policy (appointments,
     *                 appointment_change_requests) and never blocks
     *                 account maintenance.
     *
     * NO demographic identity columns and no is_current / current_pregnancy_id
     * are stored here - this table records pairings, never how they were
     * inferred, so a demographic matcher can never be reintroduced through
     * the schema.
     *
     * The migration FILE is created by this phase; executing it against
     * local MySQL or Railway requires separate explicit approval. Automated
     * tests exercise it on SQLite :memory: only (RefreshDatabase). The
     * backfill below is written with portable query-builder SQL that runs
     * unchanged on SQLite in-memory and on the project's MySQL/MariaDB
     * schema.
     */
    public function up(): void
    {
        if (Schema::hasTable('user_pregnancy_links')) {
            throw new RuntimeException(
                'DEPLA Phase 3H.2 migration refused: user_pregnancy_links already exists but this '
                . 'migration did not create it, so it will not take ownership of it. A migration '
                . 'row is only written after up() returns; adopting a table created elsewhere would '
                . 'let a later rollback drop verified pregnancy-link history that belongs to '
                . 'something else. No schema changes were made. Remove this migration from the run '
                . 'or drop the existing table (after preserving its rows) before running it again.'
            );
        }

        Schema::create('user_pregnancy_links', function (Blueprint $table) {
            $table->id();

            // The login account that owns the pregnancy episode.
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            // The verified pregnancy episode. RESTRICT: relationship
            // history must never disappear through cascading deletion.
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Acting staff/admin who recorded the pairing; NULL when the
            // pairing originates from migration backfill or when no
            // verifier identity is reliably available.
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // When the pairing was recorded (provenance, not an access
            // flag - there is no is_current column by design).
            $table->timestamp('verified_at');

            $table->timestamps();

            // One row per account-to-pregnancy pairing, ever.
            $table->unique(['user_id', 'patient_id']);

            // History lookups by pregnancy episode (current pointer
            // resolution stays on patients.user_id and is unaffected).
            $table->index('patient_id');
        });

        $this->backfillExistingLinks();
    }

    /**
     * Verified-only backfill (idempotent).
     *
     * Copies EXISTING explicit account-link evidence - exactly the rows
     * where patients.user_id IS NOT NULL - into user_pregnancy_links:
     * user_id = the current pointer, patient_id = that pregnancy row,
     * verified_by = NULL (this migrates pre-existing evidence; it does NOT
     * claim a staff verification was performed), verified_at = now.
     *
     * Deliberately NEVER matches on names, birthdates, emails, contact
     * numbers, addresses or gravida/para, and never "picks the latest
     * pregnancy". patients.user_id values themselves are read-only here.
     *
     * Implementation notes:
     * - Query builder only (DB::table), never Patient models: no Eloquent
     *   events, no soft-delete scope surprises, no clinical rows loaded.
     *   A soft-deleted (archived) pregnancy with an explicit user_id is
     *   intentionally included - the relationship evidence is preserved
     *   while the existing portal middleware keeps archived accounts at
     *   403.
     * - Duplicate handling is a correlated NOT EXISTS anti-join in the
     *   SELECT itself (already-linked pairs never reach the INSERT) plus
     *   an ordinary multi-row insert() of the remaining pairs. This
     *   replaces an earlier insertOrIgnore() call: INSERT IGNORE on MySQL
     *   is a blanket ignore that also swallows truncation, range and
     *   NULL-into-NOT-NULL errors, so a silently corrupted backfill was
     *   possible; on SQLite INSERT OR IGNORE is narrower but still hides
     *   the same class of constraint surprises. The portable anti-join +
     *   plain insert combination is idempotent for re-runs AND lets any
     *   unexpected failure surface as a thrown QueryException - there is
     *   deliberately no try/catch anywhere in this method.
     * - The anti-join is NOT an atomic upsert: between the SELECT and the
     *   INSERT a concurrent process could pair the same (user_id,
     *   patient_id). That race is accepted by design - the UNIQUE
     *   (user_id, patient_id) constraint rejects the concurrent duplicate
     *   and the whole backfill run aborts loudly rather than continuing on
     *   corrupted state. Re-running the backfill after the conflict is
     *   resolved completes the remaining rows.
     * - Chunked by 200 so a large patients table never loads into memory.
     *   Only an anonymous count is returned - no names, emails, phone
     *   numbers or medical details are ever printed to logs or console.
     *
     * @return int number of relationship rows inserted (anonymous count)
     */
    public function backfillExistingLinks(): int
    {
        $timestamp = now();

        $inserted = 0;

        DB::table('patients')
            ->whereNotNull('user_id')
            // Portable correlated anti-join (identical SQL on SQLite and
            // MySQL): skip any pair that already holds a link row, so a
            // re-run or a later backfill of newly-added pointers never
            // touches, reassigns or re-timestamps existing evidence.
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->selectRaw('1')
                    ->from('user_pregnancy_links')
                    ->whereColumn('user_pregnancy_links.user_id', 'patients.user_id')
                    ->whereColumn('user_pregnancy_links.patient_id', 'patients.id');
            })
            ->select('id', 'user_id')
            ->chunkById(200, function ($patients) use ($timestamp, &$inserted) {
                $rows = $patients->map(fn (object $patient): array => [
                    'user_id' => $patient->user_id,
                    'patient_id' => $patient->id,
                    'verified_by' => null,
                    'verified_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all();

                if ($rows === []) {
                    return;
                }

                // Ordinary insert(), never insertOrIgnore(): a concurrent
                // duplicate or any unexpected constraint violation throws
                // QueryException out of this migration instead of being
                // silently discarded.
                DB::table('user_pregnancy_links')->insert($rows);

                $inserted += count($rows);
            });

        return $inserted;
    }

    /**
     * Rollback refuses to drop a populated user_pregnancy_links table -
     * the same history-preservation guard used by the appointments and
     * pregnancy_outcomes migrations. Verified ownership relationships are
     * medical-record linkage evidence: silently destroying them (or
     * rewriting patients.user_id, which this method never touches) would
     * sever the audit trail of which account was verified against which
     * pregnancy. An empty table (never used) drops normally so up() can
     * run again.
     */
    public function down(): void
    {
        if (! Schema::hasTable('user_pregnancy_links')) {
            return;
        }

        $rowCount = DB::table('user_pregnancy_links')->count();

        if ($rowCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback user_pregnancy_links migration: {$rowCount} row(s) hold persisted "
                . 'verified pregnancy-link history. Dropping the table would destroy recorded '
                . 'account-to-pregnancy ownership evidence. Rollback was cancelled and the table '
                . 'was left intact - no rows were deleted, converted, or rewritten, and '
                . 'patients.user_id pointers were not modified.'
            );
        }

        Schema::dropIfExists('user_pregnancy_links');
    }
};
