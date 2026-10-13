<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPLA Phase 3B.1 - appointments foundation (single entity, service
     * type discriminator).
     *
     * PURPOSE
     * -------
     * One additive `appointments` table that can represent all three clinic
     * request kinds - checkup, counseling and delivery_request - without any
     * clinical fields, capacity fields or admission semantics.
     *
     * DESIGN DECISIONS (Phase 3B.1 inspection + confirmed requirements)
     * -----------------------------------------------------------------
     * - DELIVERY CAPACITY IS DELIBERATELY ABSENT. A delivery request is a
     *   REQUEST, never an admission. There is no bed, occupancy, admitted_at
     *   or discharge column here, and no two-bed counter. Actual bed
     *   occupancy will live in a separate future entity; a confirmed
     *   delivery request must never automatically occupy a bed, and no
     *   emergency request may ever be auto-rejected by this table.
     * - NO SCHEDULING ENFORCEMENT. requested_date/requested_time store the
     *   patient's preferred schedule; confirmed_date/confirmed_time store
     *   the clinic-approved schedule. They are separate concepts and the
     *   8:00-17:00 Philippine-time window is NOT validated here (same-day
     *   booking, advance limits, duplicate rules and counseling duration
     *   remain unresolved client questions). No fixed slots, no daily
     *   capacity columns.
     * - TYPES/STATUS ARE strings, NOT database enums. The one existing
     *   enum with a lifecycle (`referrals.status`) already needed an ALTER
     *   migration to grow a value; newer project vocabularies
     *   (patients.status, pregnancy_outcomes.outcome_type) use string
     *   columns validated against application constants. Same approach:
     *   values live on the Appointment model (checkup|counseling|
     *   delivery_request, pending|confirmed|completed|cancelled|declined,
     *   online|walk_in).
     * - REFERENTIAL POLICY: patients.id is ON DELETE RESTRICT. Appointment
     *   history must never be silently destroyed by a patient hard delete,
     *   and patient_id stays required so an appointment always belongs to a
     *   real patient. (Patients are soft-deleted in normal operation -
     *   soft deletes never trigger this constraint. A future hard delete
     *   must explicitly archive/delete the appointments first, in the open,
     *   rather than cascading invisibly. Note: pregnancies_outcomes uses
     *   cascade by design - appointments deliberately differ because they
     *   are an operational request log, not clinical outcome evidence.)
     * - reviewed_by is ON DELETE SET NULL: reviewer provenance follows the
     *   existing policy (refusers/confirmers keep their record when a staff
     *   account is removed) and must never block anything.
     * - Soft deletes (deleted_at) follow the record-retention convention of
     *   every other workflow table (patients, prenatal_visits, referrals,
     *   ultrasounds, medical_histories, birth_plans, users), so a cancelled
     *   or mistaken row can be archived without being destroyed.
     * - NO PATIENT NAME/CONTACT COLUMNS. The future Admin/Staff calendar
     *   resolves patient name, contact number and profile link through the
     *   patient() relationship; duplicating them here would drift from the
     *   patients record.
     *
     * The migration FILE is created by this phase; executing it against
     * local MySQL or Railway requires separate explicit approval. Automated
     * tests exercise it on SQLite :memory: only (RefreshDatabase).
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // Required ownership: an appointment always belongs to an
            // existing patient (portal accounts, walk-ins and future
            // staff-entered requests all resolve to a patients row first).
            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            // Service discriminator: checkup | counseling | delivery_request.
            $table->string('appointment_type', 32);

            // Booking origin: online | walk_in.
            $table->string('source', 16);

            // Patient's preferred schedule (requested) vs clinic-approved
            // schedule (confirmed). Time is nullable so a delivery request
            // can be date-only; both confirmed fields start NULL.
            $table->date('requested_date');
            $table->time('requested_time')->nullable();
            $table->date('confirmed_date')->nullable();
            $table->time('confirmed_time')->nullable();

            // pending | confirmed | completed | cancelled | declined.
            $table->string('status', 32)->default('pending');

            // Review provenance (admin/staff confirmation or decline).
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('decline_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Calendar/queue access paths: filter by status and type, range
            // over either schedule column, and list a patient's requests.
            $table->index('status');
            $table->index('appointment_type');
            $table->index('requested_date');
            $table->index('confirmed_date');
            $table->index(['patient_id', 'status']);
        });
    }

    /**
     * Rollback refuses to drop a populated appointments table - the same
     * history-preservation guard used by the pregnancy_outcomes migration.
     * An empty table (never used) drops normally, so up() can run again.
     */
    public function down(): void
    {
        if (! Schema::hasTable('appointments')) {
            return;
        }

        $rowCount = DB::table('appointments')->count();

        if ($rowCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback appointments migration: {$rowCount} row(s) hold persisted "
                . 'appointment request history. Dropping the table would destroy recorded '
                . 'appointment data. Rollback was cancelled and the table was left intact - '
                . 'no rows were deleted or converted.'
            );
        }

        Schema::dropIfExists('appointments');
    }
};
