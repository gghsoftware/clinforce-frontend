<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiScreening extends Model
{
    protected $table = 'ai_screenings';

    public $timestamps = false; // only created_at exists

    protected $fillable = [
        'application_id',
        'job_id',
        'applicant_user_id',
        'model_name',
        'score',
        'summary',
        'suggestions',
        'created_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'suggestions' => 'array',
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }
}
