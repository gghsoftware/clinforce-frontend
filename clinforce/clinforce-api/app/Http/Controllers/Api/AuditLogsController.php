<?php
// app/Http/Controllers/Api/AuditLogsController.php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;

class AuditLogsController extends ApiController
{
    // Admin-only list
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'admin') return $this->fail('Forbidden', null, 403);

        $q = AuditLog::query()->orderByDesc('id');

        if ($entityType = request()->query('entity_type')) {
            $q->where('entity_type', trim((string)$entityType));
        }
        if ($entityId = request()->query('entity_id')) {
            if (!ctype_digit((string)$entityId)) {
                return $this->fail('Invalid entity_id', ['entity_id' => ['Must be integer']], 422);
            }
            $q->where('entity_id', (int)$entityId);
        }
        if ($actorId = request()->query('actor_user_id')) {
            if (!ctype_digit((string)$actorId)) {
                return $this->fail('Invalid actor_user_id', ['actor_user_id' => ['Must be integer']], 422);
            }
            $q->where('actor_user_id', (int)$actorId);
        }

        return $this->ok($q->paginate(50));
    }
}
