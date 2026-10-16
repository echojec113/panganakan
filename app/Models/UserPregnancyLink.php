<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DEPLA Phase 3H.2 - verified user-to-pregnancy relationship.
 *
 * One row per EXPLICIT account-to-pregnancy pairing ever recorded for a
 * patient episode: the durable multi-pregnancy ownership evidence that
 * complements (but never replaces) the patients.user_id CURRENT-pregnancy
 * pointer from Phase 2A.
 *
 * ARCHITECTURE BOUNDARY
 * ---------------------
 * - This model does NOT select the current pregnancy. The portal keeps
 *   resolving through User::patientAccount() (patients.user_id). A link
 *   row alone never grants Patient Portal route access.
 * - There is no is_current / current_pregnancy_id helper and no
 *   demographic-matching helper: links are created only explicitly
 *   (PatientAccountCreationService today; an approved verification
 *   workflow later) and are always auditable through verified_by /
 *   verified_at. No model hooks attach pregnancies automatically.
 *
 * MASS-ASSIGNMENT HARDENING
 * -------------------------
 * Everything is guarded: user_id, patient_id, verified_by and verified_at
 * are ownership/provenance columns written only by explicit property
 * assignment inside the authorizing service transaction (the Phase 2B
 * patients.user_id precedent). Test fixtures use direct property writes
 * or forceCreate() - never an unrestricted create() from request data.
 *
 * DELETION POLICY (schema-enforced, see migration)
 * ------------------------------------------------
 * user_id and patient_id are RESTRICT (history never silently destroyed);
 * verified_by is SET NULL (verifier provenance survives account removal).
 */
class UserPregnancyLink extends Model
{
    /** No mass-assignment surface: every column is ownership or provenance. */
    protected $guarded = ['*'];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /** The login account that owns this verified pregnancy pairing. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The verified pregnancy episode (a row of patients). */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * The staff/admin account that recorded the pairing (nullable: NULL for
     * migration backfill of pre-existing evidence or when no verifier
     * identity is reliably available). SET NULL on user removal.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
