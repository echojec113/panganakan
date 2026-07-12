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

        'assessment',
        'recommendation',
        'treatment_plan',
        'next_visit_date',
        'reminder_tomorrow_sent_at',
        'reminder_today_sent_at',

        'notes'
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
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime'
];

    public function patient()
    {
        return $this->belongsTo(\App\Models\Patient::class);
    }

    public function getMonitoringNextVisitLabel(): string
    {
        $patientStatus = $this->patient?->status;

        if ($patientStatus === 'DELIVERED') {
            return 'Delivered';
        }

        if ($patientStatus === 'REFERRED') {
            return 'Referred';
        }

        if ($this->next_visit_date) {
            return Carbon::parse($this->next_visit_date)->format('M d, Y');
        }

        return 'Not scheduled';
    }

    public function isMonitoringOverdue(): bool
    {
        $patientStatus = $this->patient?->status;

        if (in_array($patientStatus, ['DELIVERED', 'REFERRED'], true)) {
            return false;
        }

        return (bool) ($this->next_visit_date && Carbon::parse($this->next_visit_date)->isPast());
    }
    
}