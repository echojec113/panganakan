<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DEPLA Phase 3D.2 - appointment change requests (patient cancellation
 * requests; reschedule requests arrive with Phase 3D.3 on the same
 * vocabulary).
 *
 * The model is a data holder ONLY: no cutoff checks, no one-pending
 * enforcement, no approve/decline logic lives here (per-phase scope -
 * those belong to AppointmentManagementService under the appointment
 * row lock, so concurrency safety has a single owner).
 *
 * Vocabulary constants follow the project's application-side validation
 * style (App\Support\PregnancyOutcomeVocabulary precedent, and
 * App\Models\Appointment's type/source/status constants).
 *
 * Time display note: every timestamp rendered from this model (created_at
 * as the requested time, reviewed_at) is converted to Manila at the view
 * layer, matching the Phase 3B.4 notification UI convention.
 */
class AppointmentChangeRequest extends Model
{
    /** Request kinds (type). */
    public const TYPE_CANCELLATION = 'cancellation';
    public const TYPE_RESCHEDULE = 'reschedule';

    public const TYPES = [
        self::TYPE_CANCELLATION,
        self::TYPE_RESCHEDULE,
    ];

    public const TYPE_LABELS = [
        self::TYPE_CANCELLATION => 'Cancellation Request',
        self::TYPE_RESCHEDULE => 'Reschedule Request',
    ];

    /**
     * Review statuses.
     *
     * 'pending' exists only while the appointment itself is still
     * confirmed: direct staff cancellation/completion auto-closes any
     * pending request in the same transaction (service invariant, not a
     * schema constraint).
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Pending Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_DECLINED => 'Declined',
    ];

    /**
     * DEPLA Phase 3D.4B (P3) - mass-assignment HARDENING.
     *
     * Only the PATIENT-supplied proposal content is mass-assignable.
     * The OWNING and REVIEW columns are excluded (appointment_id, type,
     * status, reviewed_by, reviewed_at, review_note) and are written
     * exclusively by AppointmentManagementService through explicit
     * property assignment inside the appointment lock transaction.
     *
     * DEFENSE NOTES
     * - A forged mass-assignment can no longer point a request at
     *   somebody else's appointment, change its type, or pre-approve /
     *   pre-decline it with a fake reviewer - which would otherwise let
     *   a patient cancel or reschedule without clinic review, the exact
     *   control Phase 3D.2B/3D.3B introduced.
     * - review_note is staff-authored review output and is set only by
     *   the staff review service, never by patient input.
     * - No production code mass-assigns this model today, so clinical
     *   behaviour is unchanged; test fixtures use forceCreate().
     */
    protected $fillable = [
        'proposed_date',
        'proposed_time',
        'reason',
    ];

    /**
     * Date columns are cast so every consumer compares Y-m-d through
     * Carbon (matching the Appointment model's requested_date/
     * confirmed_date convention). Time columns stay strings: they are
     * plain clock values with no date to anchor, and the UI formats them
     * for display.
     */
    protected $casts = [
        'proposed_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    /**
     * The appointment this request targets. Created_at on this row is the
     * requested timestamp; the appointment's confirmed schedule stays
     * untouched while this request is pending.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * The admin/staff member who approved or declined the request
     * (nullable while pending). SET NULL on user removal, so review
     * provenance survives a deleted account. Deliberately separate from
     * Appointment::reviewer() (the original confirmer) - approving a
     * cancellation must never overwrite the appointment's reviewed_by /
     * reviewed_at.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Human label for the request type (portal cards, calendar panel,
     * notification messages).
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Human label for the review status.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
