<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPLA Phase 3D.2 - appointment change requests (patient cancellation
     * requests; reschedule requests arrive with Phase 3D.3 on the same
     * table).
     *
     * PURPOSE
     * -------
     * One additive table that records PATIENT-initiated change requests
     * against existing appointments. The appointments table itself is
     * untouched: a confirmed appointment stays confirmed (with its
     * confirmed schedule intact) until Admin/Staff explicitly approve a
     * pending request.
     *
     * WORKFLOW RULES ENCODED HERE (Phase 3D.2A design, user-approved)
     * -----------------------------------------------------------------
     * - Pending ONLINE appointments: the owning patient may cancel
     *   DIRECTLY (no approval); that mutation never touches this table.
     * - Confirmed ONLINE appointments: the owning patient may submit a
     *   CANCELLATION REQUEST (approval-gated) or, in Phase 3D.3, a
     *   RESCHEDULE REQUEST. Walk-in appointments are excluded entirely
     *   (patient-side mutations apply only to source=online; enforced in
     *   the service, not here).
     * - ONE PENDING REQUEST PER APPOINTMENT, across both types. The
     *   duplicate/concurrency guard is LOCK-FIRST, NOT a database
     *   constraint: every operation locks the appointment row
     *   (lockForUpdate) before reading/inserting request rows, so
     *   concurrent submissions serialize on the appointment lock and the
     *   in-transaction "no pending row exists" check is race-free.
     *   A plain unique(appointment_id, status) is WRONG (it would cap a
     *   lifetime at one approved request, violating unlimited
     *   rescheduling), and MySQL (production) does not support partial
     *   unique indexes (WHERE status='pending', SQLite-only). The
     *   portable pending_slot NULL-pattern remains available later if
     *   real-world incidents justify belt-and-braces.
     * - Requests are only accepted before the appointment's scheduled
     *   start (Manila), re-checked at request time AND review time in
     *   the service - not enforced by any column here.
     * - Auto-resolution (approved): when Staff cancel or complete an
     *   appointment that still has a pending request, the service closes
     *   the request in the SAME transaction (cancellation request ->
     *   approved; reschedule request -> declined), preserving the
     *   invariant "a pending request exists only while its appointment
     *   is confirmed". Service logic, not schema.
     *
     * DESIGN DECISIONS
     * ----------------
     * - type/status are strings, NOT database enums - same rationale as
     *   appointments.status (referrals' DB enum already needed an ALTER
     *   migration to grow a value). Values live on the
     *   AppointmentChangeRequest model (cancellation|reschedule,
     *   pending|approved|declined).
     * - REFERENTIAL POLICY: appointments.id is ON DELETE RESTRICT -
     *   request history is never destroyed by deleting an appointment
     *   row (same policy as appointments.patient_id). users.id (reviewed_by)
     *   is ON DELETE SET NULL - reviewer provenance follows the existing
     *   policy and never blocks anything.
     * - NO separate requested_at column: created_at IS the requested
     *   timestamp (every other DEPLA entity relies on created_at; a
     *   duplicate column would drift). It is rendered in Manila time at
     *   display, matching the notification UI convention.
     * - NO requested_by column: the submitter is always the appointment's
     *   own patient, resolved server-side from the authenticated portal
     *   account - never from input. Actor provenance lives in audit_logs
     *   (user_id), the established single source.
     * - NO soft deletes: resolved requests are immutable, low-volume
     *   history; a deleted_at scope would complicate the one-pending
     *   invariant and destroy review history. Archival (if ever needed)
     *   belongs in a future explicit admin tool.
     * - proposed_date/proposed_time are NULLABLE for cancellation
     *   requests (the schedule columns describe the PROPOSED replacement
     *   schedule and only reschedule requests carry one). Time stays
     *   nullable so a date-only delivery reschedule remains legal, and
     *   the proposed schedule itself is validated by the service/Form
     *   Requests (past rule + clinic hours) at request and review time -
     *   not by columns here.
     * - reason is nullable because reschedule reasons are optional
     *   (patient-approved rule); cancellation reasons are REQUIRED by
     *   the FormRequest/service, not by the schema.
     * - reviewed_at/reviewed_by/review_note are NULL while the request is
     *   pending; approval/decline fills them. Appointment
     *   reviewed_by/reviewed_at (the original confirmer provenance) is
     *   NEVER touched by request approval - the approver's identity is
     *   recorded here and in the audit log.
     *
     * The migration FILE is created by this phase; executing it against
     * local MySQL or Railway requires separate explicit approval. Automated
     * tests exercise it on SQLite :memory: only (RefreshDatabase).
     */
    public function up(): void
    {
        Schema::create('appointment_change_requests', function (Blueprint $table) {
            $table->id();

            // The appointment the request targets. RESTRICT: request
            // history must never be silently destroyed.
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->restrictOnDelete();

            // cancellation | reschedule - application constants.
            $table->string('type', 16);

            // Proposed replacement schedule. NULL for cancellation
            // requests; required date + nullable time for reschedule.
            $table->date('proposed_date')->nullable();
            $table->time('proposed_time')->nullable();

            // Patient-submitted reason. Required for cancellation by
            // application rule; optional for reschedule.
            $table->text('reason')->nullable();

            // pending | approved | declined - application constants.
            $table->string('status', 16)->default('pending');

            // Review provenance (admin/staff approval or decline).
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            // Optional reviewer note; auto-resolution writes a fixed
            // explanatory note here when it closes a stale request.
            $table->text('review_note')->nullable();

            // created_at serves as the requested timestamp (no duplicate
            // requested_at column - see docblock).
            $table->timestamps();

            // Access paths: list a change request's appointment history
            // (calendar details map, portal history), and the
            // one-pending lookup (appointment_id + status) that runs
            // under the appointment row lock.
            $table->index('appointment_id');
            $table->index('status');
            $table->index(['appointment_id', 'status']);
        });
    }

    /**
     * Rollback refuses to drop a populated appointment_change_requests
     * table - the same history-preservation guard used by the
     * appointments and pregnancy_outcomes migrations. An empty table
     * (never used) drops normally, so up() can run again.
     */
    public function down(): void
    {
        if (! Schema::hasTable('appointment_change_requests')) {
            return;
        }

        $rowCount = DB::table('appointment_change_requests')->count();

        if ($rowCount > 0) {
            throw new \RuntimeException(
                "Cannot rollback appointment_change_requests migration: {$rowCount} row(s) hold persisted "
                . 'change-request history. Dropping the table would destroy recorded cancellation/'
                . 'reschedule request data. Rollback was cancelled and the table was left intact - '
                . 'no rows were deleted or converted.'
            );
        }

        Schema::dropIfExists('appointment_change_requests');
    }
};
