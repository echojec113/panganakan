<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicalHistory extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [

        'patient_id',
        'epilepsy',
        'severe_headache',
        'visual_disturbance',
        'chest_pain',
        'shortness_breath',
        'breast_mass',
        'liver_disease',
        'smoking',
        'allergies',
        'drug_intake',
        'std_history',
        'diabetes',
        'hypertension',
        'asthma',
        'thyroid_disease',
        'heart_disease',
        'anemia',
        'mental_health_condition',
        'other_specify',

    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}