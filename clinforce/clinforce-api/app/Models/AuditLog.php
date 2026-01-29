<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    public $timestamps = false; // only created_at exists

    protected $fillable = [
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'meta',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
