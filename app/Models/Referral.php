<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'patient_id',
        'prenatal_visit_id',
        'created_by',
        'referred_to',
        'doctor_name',
        'reason',
        'notes',
        'referral_date',
        'status',
        'completed_at',
        'assessment_snapshot',
        'waiver_signed',
        'refusal_recorded_at',
        'refusal_recorded_by',
        'refusal_notes',
    ];

    protected $casts = [
        'referral_date'       => 'date',
        'completed_at'        => 'datetime',
        'assessment_snapshot' => 'array',
        'waiver_signed'       => 'boolean',
        'refusal_recorded_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function prenatalVisit()
    {
        return $this->belongsTo(PrenatalVisit::class, 'prenatal_visit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function refusalRecordedBy()
    {
        return $this->belongsTo(User::class, 'refusal_recorded_by');
    }
}
