<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Baby extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'date_of_birth',
        'time_of_birth',
        'birth_weight',
        'birth_length',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'time_of_birth' => 'datetime:H:i',
        'birth_weight' => 'decimal:2',
        'birth_length' => 'decimal:1',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name);
    }
}
