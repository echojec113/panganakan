<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEPLA Phase 1 (appointment scheduling upgrade) - clinic unavailability
     * foundation.
     *
     * PURPOSE
     * -------
     * One additive table describing ADMIN-declared periods during which the
     * clinic does not accept new appointment bookings. Three approved
     * closure shapes share this single table:
     *   - full-day single date  (all_day=true, start_date = end_date)
     *   - continuous date range (all_day=true, end_date >= start_date,
     *     inclusive on both ends)
     *   - partial time window   (all_day=false, single date only,
     *     start_time inclusive / end_time EXCLUSIVE, 30-minute grid)
     * A slot-level closure on several non-contiguous times of one date is
     * stored as MULTIPLE single-day window rows; no child-slots table and no
     * JSON columns exist, so edit / remove / reopen stay plain row-level
     * operations.
     *
     * DESIGN DECISIONS (approved Phase 1A architecture)
     * -------------------------------------------------
     * - UNION SEMANTICS, OVERLAPS ALLOWED. Overlapping rows are permitted on
     *   purpose: occupancy is the UNION of all windows covering a date. No
     *   merge-on-write or split-on-edit logic lives in the schema; the
     *   service computes the merged windows on read.
     * - CROSS-FIELD RULES ARE APPLICATION-LEVEL. Portable partial
     *   constraints (end_date >= start_date via a CHECK with ? operator,
     *   all_day=false => times required, time windows single-day only)
     *   behave differently or are awkward on SQLite vs MySQL/MariaDB, so
     *   they are validated in ClinicAvailabilityService::validateClosureInput()
     *   against one shared rule set - the same place the future
     *   FormRequest rules are sourced from. The schema only carries the
     *   structural columns.
     * - reason is OPTIONAL and ADMIN-INTERNAL by approved policy (never
     *   surfaced to patients by any default serialization path; the model
     *   deliberately defines no patient-facing accessor for it).
     * - created_by is ON DELETE SET NULL: closure provenance follows the
     *   reviewer-provenance convention (appointment reviewed_by) and must
     *   never block anything. The permanent WHO/WHAT history is the
     *   audit_logs row written in the same transaction as the closure
     *   change (CLINIC_UNAVAILABILITY_CREATED|UPDATED|REMOVED).
     * - HARD DELETION, NO SOFT DELETES. Reopening a blocked date is the
     *   explicit removal of its closure row(s); audit_logs retain the
     *   history (same precedent as appointment_change_requests). Closures
     *   are clinic configuration, not clinical or request records.
     * - ADDITIVE ONLY. No existing table is touched. Existing appointments
     *   are never modified, cancelled or rescheduled by a closure - the
     *   future booking/confirm paths merely REFUSE new writes into closed
     *   windows; already-scheduled appointments are flagged for manual
     *   staff review in a later phase.
     *
     * The migration FILE is created by this phase; executing it against
     * local MySQL or Railway requires separate explicit approval. Automated
     * tests exercise it on SQLite :memory: only (RefreshDatabase).
     */
    public function up(): void
    {
        Schema::create('clinic_unavailabilities', function (Blueprint $table) {
            $table->id();

            // Inclusive calendar range. end_date = start_date for single-day
            // closures (mandatory when all_day=false; typical otherwise).
            $table->date('start_date');
            $table->date('end_date');

            // true = the whole day(s) are closed (multi-day allowed).
            // false = a partial window on ONE date (start_time..end_time,
            // end exclusive), validated by the service.
            $table->boolean('all_day')->default(true);

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            // Optional admin-internal note (max 255). Never patient-facing.
            $table->string('reason', 255)->nullable();

            // Who declared the closure. SET NULL on account removal.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Date-coverage lookup: any closure touching a calendar date or
            // range (start_date <= query_end AND end_date >= query_start).
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Closures are clinic CONFIGURATION: dropping the table restores the
     * pre-Phase-1 state (every date bookable) without touching a single
     * appointment row, and audit_logs retain the full create/update/remove
     * history. No populated-history guard is needed (unlike appointments).
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_unavailabilities');
    }
};
