<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPLA Phase 1 (appointment scheduling upgrade) - appointment slot
     * hold ledger (the database-level concurrency barrier).
     *
     * PURPOSE
     * -------
     * One row per CONFIRMED (or COMPLETED) timed appointment, recording the
     * exact 30-minute grid slot it reserves:
     *   - UNIQUE (hold_date, slot_start): at most ONE hold per slot -
     *     two concurrent confirmations/walk-ins targeting the same slot
     *     cannot both succeed; the loser gets a unique-constraint
     *     violation translated to a clean DomainException.
     *   - UNIQUE (appointment_id): one active hold per appointment - a
     *     reschedule moves (UPDATEs) the single row, it never inserts a
     *     second one.
     *
     * DESIGN DECISIONS (approved Phase 1A architecture)
     * -------------------------------------------------
     * - THE UNIQUE INDEX, NOT lockForUpdate(), IS THE PRIMARY GUARANTEE.
     *   lockForUpdate() is a no-op on SQLite and only protects what it
     *   locks; the unique index serializes conflicting INSERT/UPDATE at
     *   the engine level on MySQL/MariaDB AND SQLite. Callers still use
     *   the existing appointment-then-request lock discipline for state
     *   transitions; the hold write is the LAST write in that order.
     * - NO BACKFILL. Historical appointments get NO hold rows in this
     *   phase (multiple legacy confirmed appointments may legally share
     *   one time window today - backfilling would violate the unique
     *   index and rewriting history is forbidden). Occupancy detection
     *   therefore ALWAYS also inspects existing confirmed/completed
     *   appointment rows (legacy-window query in
     *   ClinicAvailabilityService), not only this table. The holds table
     *   is the authoritative barrier for NEW grid-aligned reservations;
     *   the legacy-window query closes the read side for old rows.
     *   Documented residual limitation: a legacy non-grid appointment
     *   without a hold cannot be prevented from double-booking at the
     *   database level (the read-side query + caller transaction is the
     *   best available defense for those rows).
     * - PENDING APPOINTMENTS NEVER HOLD SLOTS (approved rule). Hold rows
     *   are written on confirmation / immediate walk-in confirmation
     *   only; cancelled and declined appointments release their hold;
     *   completed appointments KEEP theirs (completed retains
     *   occupancy).
     * - DELIVERY REQUESTS NEVER GET HOLDS. They remain date-only in the
     *   scheduling model (no 30-minute slot semantics); the service
     *   refuses to reserve/move holds for them. They also do not
     *   occupy slots.
     * - TIME STORED AS H:i CANONICAL VALUES (matching normalizeTime() in
     *   AppointmentManagementService). MySQL TIME columns accept H:i and
     *   read back H:i:s; the service normalizes on write and compares on
     *   zero-padded values. SQLite stores the H:i string as written.
     * - CASCADE ON DELETE: a hard-deleted appointment (not normal
     *   operation - appointments are soft-deleted) must not leave an
     *   orphan hold blocking its slot forever.
     * - Holds are DERIVED STATE, not source of truth: the appointment row
     *   remains authoritative for display; dropping this table reverts
     *   to today's lock-only behavior with zero data loss.
     *
     * The migration FILE is created by this phase; executing it against
     * local MySQL or Railway requires separate explicit approval, and NO
     * backfill is run anywhere. Automated tests exercise it on SQLite
     * :memory: only (RefreshDatabase).
     */
    public function up(): void
    {
        Schema::create('appointment_slot_holds', function (Blueprint $table) {
            $table->id();

            // Calendar date of the reserved slot (canonical Y-m-d).
            $table->date('hold_date');

            // Canonical H:i slot START (08:00 .. 16:30 on the approved
            // 30-minute grid; the slot ends 30 minutes later).
            $table->time('slot_start');

            // The appointment this hold reserves for. 1:1 (unique below).
            $table->foreignId('appointment_id')
                ->constrained('appointments')
                ->cascadeOnDelete();

            $table->timestamps();

            // The concurrency barrier: one confirmed appointment per slot.
            $table->unique(['hold_date', 'slot_start'], 'appointment_slot_holds_date_slot_unique');

            // One active hold per appointment (reschedule = UPDATE in place).
            $table->unique('appointment_id', 'appointment_slot_holds_appointment_unique');
        });
    }

    /**
     * Holds are derived state. Dropping the table restores the pre-Phase-1
     * behavior (no database-level slot barrier) without touching any
     * appointment row; no populated-history guard applies.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_slot_holds');
    }
};
