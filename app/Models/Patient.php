<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PrenatalVisit;
use App\Models\MedicalHistory;
use App\Models\BirthPlan;
use App\Models\Ultrasound;
use App\Models\Baby;
use App\Models\Referral;
use App\Models\PregnancyOutcome;
use App\Models\Appointment;

class Patient extends Model
{
    use HasFactory,SoftDeletes;

    protected $casts = [
        'lmp' => 'date',
        'edd' => 'date',
        'birthdate' => 'date',
        'delivery_date' => 'date',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'age',
        'address',
        'address_line',
        'barangay',
        'city_municipality',
        'contact_number',
        'email',
        'gravida',
        'para',
        'previous_cs',
        'miscarriage',
        'lmp',
        'edd',
        'middle_name',
        'birthdate',
        'civil_status',
        'philhealth_member',
        'philhealth_number',
        'status',
        'delivery_date',
        'assigned_staff_id',
    ];


    // =========================
    // 🔥 CASCADE SOFT DELETE
    // =========================
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($patient) {

            if ($patient->isForceDeleting()) {
                // permanent delete
                $patient->prenatalVisits()->forceDelete();
                $patient->ultrasounds()->forceDelete();
                $patient->babies()->forceDelete();

                if ($patient->birthPlan) {
                    $patient->birthPlan->forceDelete();
                }

                if ($patient->medicalHistory) {
                    $patient->medicalHistory->forceDelete();
                }

            } else {
                // soft delete
                $patient->prenatalVisits()->delete();
                $patient->ultrasounds()->delete();
                $patient->babies()->delete();

                if ($patient->birthPlan) {
                    $patient->birthPlan->delete();
                }

                if ($patient->medicalHistory) {
                    $patient->medicalHistory->delete();
                }
            }

        });

        static::restoring(function ($patient) {
    $patient->prenatalVisits()->withTrashed()->restore();
    $patient->ultrasounds()->withTrashed()->restore();
    $patient->babies()->withTrashed()->restore();

    $patient->birthPlan()->withTrashed()->restore();
    $patient->medicalHistory()->withTrashed()->restore();
});
    }

    



    public function prenatalVisits(): HasMany
    {
        return $this->hasMany(PrenatalVisit::class);
    }

    /** Latest non-deleted visit by clinical date; ID breaks same-day ties. */
    public function latestPrenatalAssessment(): HasOne
    {
        return $this->hasOne(PrenatalVisit::class)->ofMany([
            'visit_date' => 'max',
            'id' => 'max',
        ], fn ($query) => $query->whereNull('deleted_at'));
    }

    public function medicalHistory(): HasOne
    {
        return $this->hasOne(MedicalHistory::class);
    }
    public function ultrasounds()
    {
    return $this->hasMany(Ultrasound::class);
    }
    public function birthPlan()
{
    return $this->hasOne(BirthPlan::class);
}
    public function babies()
    {
        return $this->hasMany(Baby::class);
    }
    public function referrals()
{
    return $this->hasMany(Referral::class);
}

/** Latest non-archived referral by clinical date; ID breaks same-day ties. */
public function latestReferral(): HasOne
{
    return $this->hasOne(Referral::class)->ofMany([
        'referral_date' => 'max',
        'id' => 'max',
    ], fn ($query) => $query->whereNull('deleted_at'));
}
    public function pregnancyOutcome(): HasOne
    {
        return $this->hasOne(PregnancyOutcome::class);
    }

    /**
     * Appointment requests owned by this patient (DEPLA Phase 3B.1).
     *
     * Deliberately NOT wired into the boot() delete/restore cascade above:
     * appointment history is an operational request log, not clinical data,
     * and Phase 3B.1 introduces no archive behavior. Soft-deleting a
     * patient therefore leaves its appointment rows intact (the FK is
     * RESTRICT only against hard deletion). Whether future phases cascade
     * archive/restore onto appointments is a separate approved decision.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /**
     * The user account this patient signs in with (DEPLA Phase 2A).
     *
     * Deliberately separate from assignedStaff(): that relation is the
     * clinical assignment, this one is the login. withTrashed() is used so
     * an archived (soft-deleted) account still resolves for audit and
     * unlinking flows instead of silently reading as "no account".
     */
    public function loginAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

public function canStartNewPregnancy(): bool
{
    return $this->isDelivered();
}

public function isOngoing(): bool
{
    return $this->status === 'ONGOING';
}

public function isDelivered(): bool
{
    return $this->status === 'DELIVERED';
}

/**
 * True when the patient has at least one referral currently in the open
 * "Pending" workflow state (Phase 16D). Used to surface a referral
 * indicator in risk monitoring without coupling it to `patient.status`:
 * referred pregnancies stay ONGOING, and only an active Pending referral —
 * never a historical closed one — influences the monitoring presentation.
 */
public function hasActiveReferral(): bool
{
    return $this->referrals()->where('status', 'Pending')->exists();
}

/**
 * Centralized address presentation for every view/export/print.
 *
 * When any structured component (address_line/barangay/city_municipality)
 * is present, it takes display precedence and is assembled into clean
 * lines with no dangling punctuation, no duplicated "Barangay" label, and
 * no empty lines for components that were never entered. When none of the
 * structured components are present, this falls back to the legacy
 * single-string `address` column so older patient records keep displaying
 * exactly as before. The legacy column itself is never modified by this
 * accessor.
 */
public function getFormattedAddressAttribute(): string
{
    $lines = [];

    $addressLine = trim((string) $this->address_line);
    if ($addressLine !== '') {
        $lines[] = $addressLine;
    }

    $barangay = trim((string) $this->barangay);
    if ($barangay !== '') {
        $lines[] = 'Barangay ' . $barangay;
    }

    $cityMunicipality = trim((string) $this->city_municipality);
    if ($cityMunicipality !== '') {
        $lines[] = $cityMunicipality;
    }

    if (empty($lines)) {
        return trim((string) $this->address);
    }

    return implode("\n", $lines);
}

/**
 * True when the patient has an address recorded in either the structured
 * fields or the legacy `address` column. Used by download/export
 * completeness checks so legacy patients never fail a check solely
 * because the newer structured fields are still empty.
 */
public function hasRecordedAddress(): bool
{
    return $this->formatted_address !== '';
}
}
