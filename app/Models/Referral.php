<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'patient_id',
        'created_by',
        'referred_to',
        'doctor_name',
        'reason',
        'notes',
        'referral_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'referral_date' => 'date',
        'completed_at'  => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
