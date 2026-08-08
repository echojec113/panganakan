<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PrenatalVisit extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [

        'patient_id',
        'visit_date',

        'bp_sys',
        'bp_dia',
        'weight',
        'temperature',

        'gestational_age',

        'fundic_height',
        'fetal_heart_tone',
        'fetal_movement',

        'presenting_part',
        'uterine_activity',

        'cervical_dilation',
        'bag_of_water',

        'hypertension',
        'diabetes',
        'anemia',

        'risk_level',
        'risk_reasons',
        'decision_source',
        'missing_records',
        'rule_reasons',
        'ml_prediction',
        'ml_valid',

        'assessment',
        'recommendation',
        'treatment_plan',
        'next_visit_date',
        'reminder_tomorrow_sent_at',
        'reminder_today_sent_at',

        'notes',

        'repeat_bp_sys',
        'repeat_bp_dia',
        'repeat_bp_recorded_at',
        'repeat_bp_recorded_by',
        'bp_verification_status',
        'urgency',
        'bp_assessment',
        'factor_evidence',
        'assessment_metadata',
    ];
    protected $casts = [
    'visit_date' => 'date',
    'next_visit_date' => 'date',
    'reminder_tomorrow_sent_at' => 'datetime',
    'reminder_today_sent_at' => 'datetime',
    'hypertension' => 'boolean',
    'diabetes' => 'boolean',
    'anemia' => 'boolean',
    'risk_reasons' => 'array',
    'missing_records' => 'array',
    'rule_reasons' => 'array',
    'ml_valid' => 'boolean',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'repeat_bp_recorded_at' => 'datetime',
    'bp_assessment' => 'array',
    'factor_evidence' => 'array',
    'assessment_metadata' => 'array',
];

    public function patient()
    {
        return $this->belongsTo(\App\Models\Patient::class);
    }

    public function referrals()
    {
        return $this->hasMany(\App\Models\Referral::class, 'prenatal_visit_id');
    }

    public function repeatBpRecordedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'repeat_bp_recorded_by');
    }

    /**
     * Next-visit label for risk monitoring (Phase 16D review correction).
     *
     * A Pending referral does NOT suspend normal prenatal follow-up. For new
     * architecture patients (ONGOING + Pending referral) the normal
     * next_visit_date / overdue calculation is preserved; the referral is
     * surfaced separately in the UI as a "Pending Referral" indicator.
     * Legacy REFERRED-status rows keep their historical "Referred"
     * presentation and are never rewritten.
     */
    public function getMonitoringNextVisitLabel(): string
    {
        $patient = $this->patient;

        if ($patient?->status === 'DELIVERED') {
            return 'Delivered';
        }

        // Legacy REFERRED-status rows: keep their historical presentation.
        if ($patient?->status === 'REFERRED') {
            return 'Referred';
        }

        if ($this->next_visit_date) {
            return Carbon::parse($this->next_visit_date)->format('M d, Y');
        }

        return 'Not scheduled';
    }

    public function isMonitoringOverdue(): bool
    {
        $patient = $this->patient;

        if ($patient?->status === 'DELIVERED') {
            return false;
        }

        // Legacy REFERRED-status rows keep their historical "not overdue"
        // presentation. A Pending referral on an ONGOING patient never
        // suppresses prenatal overdue.
        if ($patient?->status === 'REFERRED') {
            return false;
        }

        return (bool) ($this->next_visit_date && Carbon::parse($this->next_visit_date)->isPast());
    }
    
}