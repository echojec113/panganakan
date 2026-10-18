<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DEPLA Phase 1 (appointment scheduling upgrade) - the slot-hold ledger
 * row: which 30-minute grid slot a confirmed/completed appointment
 * reserves.
 *
 * One row per appointment (UNIQUE appointment_id) and one row per slot
 * (UNIQUE hold_date + slot_start) - the database-level barrier that makes
 * concurrent confirmations of the same slot safe WITHOUT relying on
 * lockForUpdate() alone. Pending appointments never get holds; cancelled
 * and declined appointments release them; completed appointments keep
 * theirs; reschedule approval MOVES (updates in place) the single row.
 *
 * All writes go through ClinicAvailabilityService hold helpers (inside the
 * caller's transaction); this model is a data holder.
 */
class AppointmentSlotHold extends Model
{
    /**
     * FULLY GUARDED (empty fillable). Every column is server-owned and is
     * assigned explicitly by ClinicAvailabilityService - a forged
     * create()/fill() can never plant, re-point or re-parent a hold.
     * Follows the Appointment Phase 3D.4B hardening doctrine (holds are
     * even stricter because there is no patient-submitted content at all).
     */
    protected $fillable = [];

    /**
     * NO date cast on hold_date, deliberately: every lookup is a plain
     * equality predicate aimed straight at the UNIQUE (hold_date,
     * slot_start) index, and a Carbon 'date' cast serializes to
     * 'Y-m-d 00:00:00' on SQLite (breaking equality while whereDate-style
     * functions cannot use the index on MySQL). hold_date and slot_start
     * are therefore both stored as the canonical strings written by
     * ClinicAvailabilityService (Y-m-d and H:i).
     */

    /** The appointment this hold reserves for (CASCADE on hard delete). */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
