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
    public function pregnancyOutcome(): HasOne
    {
        return $this->hasOne(PregnancyOutcome::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
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
