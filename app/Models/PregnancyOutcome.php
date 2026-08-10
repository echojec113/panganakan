<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 17B — one pregnancy-outcome/follow-up record per patient row
 * (a patient row is one pregnancy episode). Data foundation only: no
 * recording workflow exists yet.
 *
 * Outcome confirmation is represented WITHOUT a redundant boolean:
 *   confirmed  <=>  outcome_type != null
 *                && confirmed_at set
 *                && confirmation_source set.
 * confirmed_by is OPTIONAL provenance: it is a nullable FK with
 * nullOnDelete(), so removing a staff account must never retroactively
 * un-confirm a historical outcome (see hasConfirmedOutcome()).
 * A record with all fact columns null means "no observed evidence yet" —
 * never an inferred outcome.
 */
class PregnancyOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'outcome_type',
        'delivery_location',
        'follow_up_status',
        'follow_up_recorded_at',
        'follow_up_recorded_by',
        'confirmation_source',
        'confirmed_at',
        'confirmed_by',
        'notes',
    ];

    protected $casts = [
        'follow_up_recorded_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function followUpRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_recorded_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * True when an outcome is HISTORICALLY confirmed — that is, outcome_type
     * plus the mandatory confirmation facts confirmed_at and
     * confirmation_source are all present.
     *
     * Invariant: confirmed_by is deliberately NOT required. The migration
     * declares confirmed_by as a nullable FK with nullOnDelete(), so a
     * deleted/removed staff account clears only the live FK reference — it
     * must never retroactively erase the semantic fact that the outcome was
     * historically confirmed. Removing an account does not un-confirm an
     * outcome and must never flip hasConfirmedOutcome() back to false.
     *
     * Never inferred from EDD, visit absence, referral state, or a bare
     * outcome_type. delivery_location is outcome context and is deliberately
     * not part of confirmation.
     */
    public function hasConfirmedOutcome(): bool
    {
        return $this->outcome_type !== null
            && $this->confirmed_at !== null
            && $this->confirmation_source !== null;
    }
}