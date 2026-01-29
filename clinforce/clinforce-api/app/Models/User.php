<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'role',
        'email',
        'phone',
        'password_hash',
        'status',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Make Laravel treat password_hash as the password field.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    // Profiles
    public function employerProfile(): HasOne
    {
        return $this->hasOne(EmployerProfile::class, 'user_id');
    }

    public function agencyProfile(): HasOne
    {
        return $this->hasOne(AgencyProfile::class, 'user_id');
    }

    public function applicantProfile(): HasOne
    {
        return $this->hasOne(ApplicantProfile::class, 'user_id');
    }

    // Documents
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'user_id');
    }

    // Subscriptions / Payments
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    // Jobs owned (filter by owner_type in queries)
    public function jobsOwned(): HasMany
    {
        return $this->hasMany(Job::class, 'owner_user_id');
    }

    // Applications (as applicant)
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'applicant_user_id');
    }

    // Admin / Verification / Audit
    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class, 'user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }
}
