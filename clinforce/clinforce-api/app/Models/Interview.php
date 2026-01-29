<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    protected $table = 'interviews';

    protected $fillable = [
        'application_id',
        'scheduled_start',
        'scheduled_end',
        'mode',
        'meeting_link',
        'location_text',
        'status',
        'cancel_reason',
        'created_by_user_id',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'application_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function applicant()
{
    return $this->belongsTo(User::class, 'applicant_user_id');
}
}
