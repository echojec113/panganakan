<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 3 (prenatal follow-up appointments) - link a confirmed
 * appointment back to the prenatal visit that scheduled it.
 *
 * ADDITIVE ONLY: one nullable FK, no existing column is touched, and no
 * existing appointment row is updated - walk-ins, patient online bookings
 * and every other historical row keep prenatal_visit_id = NULL, which is
 * exactly the "not a prenatal follow-up" state.
 *
 * - prenatal_visit_id points at the visit whose Next Visit date+time the
 *   staff confirmed. nullOnDelete preserves the appointment history (and
 *   its slot-hold/audit trail) even if the visit is ever hard-deleted;
 *   soft-deleted (archived) visits keep the link untouched, exactly like
 *   referrals.prenatal_visit_id.
 * - The link is written ONLY by PrenatalFollowUpAppointmentService under
 *   its transaction; Appointment::$fillable deliberately does NOT include
 *   it, so a mass-assigned request can never re-parent an appointment to
 *   a prenatal visit.
 *
 * No backfill. No UPDATE of existing appointment rows. No rewrite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'prenatal_visit_id')) {
                $table->foreignId('prenatal_visit_id')
                    ->nullable()
                    ->after('patient_id')
                    ->constrained('prenatal_visits')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (Schema::hasColumn('appointments', 'prenatal_visit_id')) {
                $table->dropForeign(['prenatal_visit_id']);
                $table->dropColumn('prenatal_visit_id');
            }
        });
    }
};
