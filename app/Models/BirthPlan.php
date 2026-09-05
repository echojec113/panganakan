<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BirthPlan extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [

        'patient_id',
        'planned_visits',
        'deliver_in_clinic',
        'delivery_location',
        'transportation',
        'transport_cost',
        'payment_method',
        'saving_started',
        'birth_companion',
        'caregiver_home',
        'plan_more_children',
        'number_more_children',
        'knows_fp_method',
        'used_fp_before',
        'family_planning_method',
        'fp_source',
        'notes'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}