<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Job extends Model
{
    protected $table = 'jobs';

    protected $fillable = [
        'owner_type',
        'owner_user_id',
        'title',
        'description',
        'employment_type',
        'work_mode',
        'country_code',
        'city',
        'status',
        'published_at',
        'archived_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    public function aiScreenings(): HasMany
    {
        return $this->hasMany(AiScreening::class, 'job_id');
    }
}
