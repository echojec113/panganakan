<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DEPLA Phase 3B.1 - appointment request foundation.
 *
 * A single entity for all three clinic request kinds, discriminated by
 * `appointment_type`. The model is a data holder ONLY: no booking approval,
 * no scheduling-window validation, no capacity or admission logic lives
 * here (per-phase scope - those belong to future FormRequests/controllers
 * and a separate future occupancy entity).
 *
 * Patient details (full name, contact number, profile link) are never
 * duplicated on this model - they resolve through patient() from the
 * existing patients record, which is what the future Admin/Staff calendar
 * will render.
 *
 * Vocabulary constants follow the project's application-side validation
 * style (see App\Support\PregnancyOutcomeVocabulary for the established
 * precedent of keeping allowed values out of the database layer).
 */
class Appointment extends Model
{
    use SoftDeletes;

    /** Service types (appointment_type). */
    public const TYPE_CHECKUP = 'checkup';
    public const TYPE_COUNSELING = 'counseling';
    public const TYPE_DELIVERY_REQUEST = 'delivery_request';

    /** Display labels for the service types (calendar chips + notifications). */
    public const TYPE_LABELS = [
        self::TYPE_CHECKUP => 'Checkup',
        self::TYPE_COUNSELING => 'Counseling',
        self::TYPE_DELIVERY_REQUEST => 'Delivery Request',
    ];

    public const TYPES = [
        self::TYPE_CHECKUP,
        self::TYPE_COUNSELING,
        self::TYPE_DELIVERY_REQUEST,
    ];

    /** Booking origin (source). */
    public const SOURCE_ONLINE = 'online';
    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCES = [
        self::SOURCE_ONLINE,
        self::SOURCE_WALK_IN,
    ];

    /**
     * Lifecycle statuses.
     *
     * 'rescheduled' is deliberately NOT a status: a reschedule updates the
     * confirmed schedule on the existing row with an audit trail in a
     * future phase. No capacity/admission statuses exist here by design.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DECLINED = 'declined';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_DECLINED,
    ];

    /**
     * DEPLA Phase 3D.4B (P3) - mass-assignment HARDENING.
     *
     * Only the PATIENT/STAFF content fields a form may legitimately
     * supply are mass-assignable. Every OWNING or CLINICAL-STATE column
     * is deliberately excluded and is written exclusively by
     * AppointmentManagementService through explicit property assignment
     * under lockForUpdate (patient_id, source, status, confirmed_date,
     * confirmed_time, reviewed_by, reviewed_at, decline_reason,
     * cancellation_reason).
     *
     * DEFENSE NOTES
     * - A forged or future request that does
     *   Appointment::create($request->all()) / ->fill($request->all())
     *   can therefore never re-parent an appointment to another patient,
     *   flip it to confirmed/cancelled, stamp a reviewer, or plant a
     *   confirmed schedule - the server-owned keys are silently dropped
     *   (fillable is non-empty, so the model is not totally guarded).
     * - No production code mass-assigns these models today; the contract
     *   exists so the next feature cannot reintroduce the hole by
     *   accident. Nothing changes for existing behaviour: every write in
     *   the service already assigns column by column.
     */
    protected $fillable = [
        'appointment_type',
        'requested_date',
        'requested_time',
    ];

    /**
     * Date columns are cast so every consumer compares Y-m-d through Carbon
     * (matching the patients/prenatal_visits convention). Time columns stay
     * strings: they are plain clock values with no date to anchor, and the
     * future UI formats them for display.
     */
    protected $casts = [
        'requested_date' => 'date',
        'confirmed_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Human label for the service type (calendar chips, notification
     * messages). Follows the Patient::getFormattedAddressAttribute
     * precedent of display helpers living on the model.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->appointment_type] ?? $this->appointment_type;
    }

    /**
     * The owning patient - the ONLY source of patient name, contact number
     * and profile data for the future calendar. No name/contact columns
     * exist on this table to duplicate.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The admin/staff member who confirmed, completed, declined or
     * otherwise reviewed the request (nullable while pending). SET NULL
     * on user removal, so review provenance survives a deleted account.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Phase 3D.2B - this appointment's CURRENTLY PENDING change request
     * (cancellation OR reschedule), across BOTH change types - the
     * one-pending invariant is per appointment, not per type. The
     * service guarantees at most one pending row (lock-first
     * one-pending check); latest('id') is a belt-and-braces tie-break
     * so the relation can never fail with multiple rows. Used by the
     * portal card (pending-review badge) and the calendar panel.
     */
    public function pendingChangeRequest(): HasOne
    {
        return $this->hasOne(AppointmentChangeRequest::class)
            ->where('status', AppointmentChangeRequest::STATUS_PENDING)
            ->latest('id');
    }

    /**
     * Phase 3D.2C - this appointment's LATEST CANCELLATION-typed change
     * request regardless of review status (pending, approved or
     * declined). Used by the patient portal card to show the OUTCOME of
     * a finished review (approved banner on the cancelled card, declined
     * explanation on the still-confirmed card) in addition to the
     * pending badge. latest('id') resolves the tie when history holds
     * more than one cancellation request over the appointment's
     * lifetime; the one-pending service invariant still guarantees at
     * most one PENDING row at any moment.
     */
    public function latestCancellationRequest(): HasOne
    {
        return $this->hasOne(AppointmentChangeRequest::class)
            ->where('type', AppointmentChangeRequest::TYPE_CANCELLATION)
            ->latest('id');
    }

    /**
     * Phase 3D.3B Step 4 - this appointment's LATEST RESCHEDULE-typed
     * change request regardless of review status. Used by the patient
     * portal card to show the OUTCOME of a finished review (approved
     * banner with the newly confirmed schedule, declined explanation on
     * the still-confirmed card) in addition to the pending badge.
     * latest('id') resolves the tie when history holds more than one
     * reschedule request over the appointment's lifetime; the
     * one-pending service invariant still guarantees at most one
     * PENDING row at any moment.
     */
    public function latestRescheduleRequest(): HasOne
    {
        return $this->hasOne(AppointmentChangeRequest::class)
            ->where('type', AppointmentChangeRequest::TYPE_RESCHEDULE)
            ->latest('id');
    }
}
