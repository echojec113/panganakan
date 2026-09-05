<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 17B — pregnancy outcome/follow-up data foundation.
     *
     * One outcome/follow-up record per pregnancy. Because each `patients` row
     * is one pregnancy episode (Start New Pregnancy clones identity into a NEW
     * row), a UNIQUE `patient_id` *is* the one-per-pregnancy contract.
     *
     * Separation of concerns:
     *   - lifecycle          -> patients.status (ONGOING / DELIVERED / legacy REFERRED)
     *   - outcome            -> outcome_type (nullable = not confirmed) + confirmation provenance
     *   - delivery context   -> delivery_location (only meaningful with a DELIVERED outcome)
     *   - follow-up          -> follow_up_status + recorded-at/by provenance
     *   - provenance         -> confirmation_source / confirmed_at / confirmed_by / notes
     *
     * Deliberate absences:
     *   - outcome_confirmed  NOT adopted. Historical confirmation is
     *     represented by outcome_type != null ALONGSIDE confirmed_at and
     *     confirmation_source, so outcome_confirmed=true + outcome_type=null
     *     is structurally impossible. confirmed_by is OPTIONAL provenance: it
     *     is a nullable FK with nullOnDelete() and may be cleared when a staff
     *     account is removed — that must never retroactively un-confirm an
     *     already recorded outcome.
     *   - delivery_date      NOT duplicated. patients.delivery_date stays the
     *     single canonical persisted actual delivery date for backward
     *     compatibility; this record owns context/provenance only.
     *
     * Data-safety: purely additive. No existing-table ALTER, no data rewrite,
     * no backfill. All outcome/provenance columns are nullable so legacy
     * DELIVERED / ONGOING / REFERRED rows without a record remain valid.
     *
     * FK policy:
     *   - patient_id:        CASCADE, matching the existing baby/child-record
     *     delete semantics (create_babies uses patient cascade). A hard-deleted
     *     patient must not leave an orphaned outcome row. Soft deletes never
     *     touch the FK because the patient row is not physically removed until
     *     forceDelete.
     *   - follow_up_recorded_by / confirmed_by: NULL on user delete. Recorded
     *     provenance must survive a removed staff account (same policy as the
     *     Sprint 16 refusal_recorded_by).
     */
    public function up(): void
    {
        Schema::create('pregnancy_outcomes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->onDelete('cascade');
            $table->unique('patient_id');

            $table->string('outcome_type')->nullable();
            $table->string('delivery_location')->nullable();

            $table->string('follow_up_status')->nullable();
            $table->timestamp('follow_up_recorded_at')->nullable();
            $table->foreignId('follow_up_recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('confirmation_source')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migration — guarded so a rollback can never destroy
     * persisted outcome/follow-up history once Sprint 17C has begun writing.
     *
     * - table absent: normal no-op.
     * - table present, empty: drop is allowed.
     * - table present with ANY row: throw before touching anything. No row
     *   deletion, no truncation, no conversion; the table stays intact.
     */
    public function down(): void
    {
        if (! Schema::hasTable('pregnancy_outcomes')) {
            return;
        }

        $rowCount = DB::table('pregnancy_outcomes')->count();

        if ($rowCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback pregnancy_outcomes migration: {$rowCount} row(s) hold persisted " .
                'pregnancy outcome/follow-up history. Dropping the table would destroy recorded ' .
                'outcome data. Rollback was cancelled and the table was left intact — no rows were ' .
                'deleted, truncated, or converted.'
            );
        }

        Schema::dropIfExists('pregnancy_outcomes');
    }
};