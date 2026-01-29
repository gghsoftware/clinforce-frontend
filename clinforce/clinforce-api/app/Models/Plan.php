<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'plans';

    public $timestamps = false; // only created_at exists

    protected $fillable = [
        'name',
        'duration_months',
        'job_post_limit',
        'ai_screening_enabled',
        'analytics_enabled',
        'price_cents',
        'currency',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'job_post_limit' => 'integer',
        'ai_screening_enabled' => 'boolean',
        'analytics_enabled' => 'boolean',
        'price_cents' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
