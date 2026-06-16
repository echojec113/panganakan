<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\PrenatalVisit;
use App\Models\MedicalHistory;
use App\Models\BirthPlan;
use App\Models\Ultrasound;
use App\Models\Baby;
use App\Models\Referral;

class Patient extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'age',
        'address',
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
}