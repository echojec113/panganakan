<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ultrasound extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'patient_id',
        'scan_date',
        'fetal_heartbeat',
        'fetal_movement',
        'presentation',
        'amniotic_fluid',
        'placenta_position',
        'gestational_age_scan',
        'estimated_fetal_weight',
        'report_file',
        'remarks'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
