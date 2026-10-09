<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable,SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'address',
        'contact_number',
        'birthday',
        'email',
        'password',
        'role',
        'profile_photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            // DEPLA Phase 2B: first-login password-change flag. Cast to a real
            // boolean so SQLite's integer 0/1 and MySQL's tinyint both read as
            // true/false. Deliberately NOT in $fillable: it is set only by
            // explicit assignment inside PatientAccountCreationService, so a
            // mass-assignment payload can never flip it on an existing account.
            'must_change_password' => 'boolean',
        ];
    }
    public function referrals()
{
    return $this->hasMany(Referral::class, 'created_by');
}

    public function assignedPatients()
    {
        return $this->hasMany(Patient::class, 'assigned_staff_id');
    }

    /**
     * The patient record linked to this login account (DEPLA Phase 2A).
     *
     * The inverse of Patient::loginAccount(). Intentionally NOT withTrashed():
     * a soft-deleted (archived) patient is not an active portal record, and
     * Patient::assignedStaff() / assignedPatients() keep the clinical
     * assignment on the separate assigned_staff_id column.
     */
    public function patientAccount(): HasOne
    {
        return $this->hasOne(Patient::class, 'user_id');
    }
}
