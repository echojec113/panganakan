<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sprint 16 — referral integration foundation.
     *
     * Adds the linkage and historical-snapshot columns referrals need to
     * consume an existing persisted prenatal assessment WITHOUT re-running
     * any clinical logic. All additions are nullable so legacy referral rows
     * (which carry only a manual reason) remain valid untouched.
     *
     * - prenatal_visit_id   links a referral to the exact assessed visit.
     *   nullOnDelete preserves the historical referral row along with its
     *   snapshot even if the visit is ever hard-deleted; soft-deleted visits
     *   (prenatal_visits uses SoftDeletes) keep the link untouched.
     * - assessment_snapshot JSON holds the immutable persisted assessment
     *   evidence copied at referral creation time (Phase 16C).
     * - refusal_* records a patient-refusal workflow (Phase 16D) with a safe
     *   FK to users that does not destroy history when a user is removed.
     * - status gains 'Refused' besides the existing Pending/Completed/Cancelled.
     *
     * No backfill. No UPDATE of existing referral rows. No rewrite.
     */
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            if (!Schema::hasColumn('referrals', 'prenatal_visit_id')) {
                $table->foreignId('prenatal_visit_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('prenatal_visits')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('referrals', 'assessment_snapshot')) {
                $table->json('assessment_snapshot')->nullable()->after('prenatal_visit_id');
            }
        });

        Schema::table('referrals', function (Blueprint $table) {
            if (!Schema::hasColumn('referrals', 'refusal_recorded_at')) {
                $table->timestamp('refusal_recorded_at')->nullable()->after('completed_at');
            }

            if (!Schema::hasColumn('referrals', 'refusal_recorded_by')) {
                $table->foreignId('refusal_recorded_by')
                    ->nullable()
                    ->after('refusal_recorded_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('referrals', 'refusal_notes')) {
                $table->text('refusal_notes')->nullable()->after('refusal_recorded_by');
            }
        });

        Schema::table('referrals', function (Blueprint $table) {
            if (Schema::hasColumn('referrals', 'status')) {
                $table->enum('status', ['Pending', 'Completed', 'Cancelled', 'Refused'])
                    ->default('Pending')
                    ->change();
            }
        });
    }

    public function down(): void
    {
        $this->assertNoRefusedRows();

        Schema::table('referrals', function (Blueprint $table) {
            if (Schema::hasColumn('referrals', 'status')) {
                $table->enum('status', ['Pending', 'Completed', 'Cancelled'])
                    ->default('Pending')
                    ->change();
            }
        });

        Schema::table('referrals', function (Blueprint $table) {
            if (Schema::hasColumn('referrals', 'refusal_recorded_by')) {
                $table->dropForeign(['refusal_recorded_by']);
                $table->dropColumn('refusal_recorded_by');
            }

            if (Schema::hasColumn('referrals', 'refusal_recorded_at')) {
                $table->dropColumn('refusal_recorded_at');
            }

            if (Schema::hasColumn('referrals', 'refusal_notes')) {
                $table->dropColumn('refusal_notes');
            }
        });

        Schema::table('referrals', function (Blueprint $table) {
            if (Schema::hasColumn('referrals', 'prenatal_visit_id')) {
                $table->dropForeign(['prenatal_visit_id']);
                $table->dropColumn('prenatal_visit_id');
            }

            if (Schema::hasColumn('referrals', 'assessment_snapshot')) {
                $table->dropColumn('assessment_snapshot');
            }
        });
    }

    /**
     * Protect historical data before rollback: if any referral row currently
     * uses the Sprint 16 'Refused' status, narrowing the enum back to
     * Pending/Completed/Cancelled could fail or silently coerce/truncate
     * persisted refusal history. In that case the rollback aborts and the
     * Phase 16B schema is left intact — no rows are changed, converted, or
     * deleted, and no partial rollback state is created.
     *
     * @throws \RuntimeException When 'Refused' referrals exist.
     */
    private function assertNoRefusedRows(): void
    {
        $refusedCount = \Illuminate\Support\Facades\DB::table('referrals')
            ->where('status', 'Refused')
            ->count();

        if ($refusedCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback referral integration migration: {$refusedCount} referral(s) currently use the 'Refused' status. " .
                'Narrowing the status enum would destroy persisted referral history. ' .
                'Rollback was cancelled and the Phase 16B schema was left intact.'
            );
        }
    }
};