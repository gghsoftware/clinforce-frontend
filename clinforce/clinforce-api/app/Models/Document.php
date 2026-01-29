<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $table = 'documents';

    public $timestamps = false; // only created_at exists in schema

    protected $fillable = [
        'user_id',
        'doc_type',
        'file_url',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'status',
        'created_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
