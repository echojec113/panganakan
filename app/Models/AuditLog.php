<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'actor_name',
        'patient_id',
        'action',
        'module',
        'description',
        'record_type',
        'record_id',
    ];

    public function user()
    {
        // Retain historical actor attribution when an account is deactivated.
        return $this->belongsTo(User::class)->withTrashed();
    }
}
