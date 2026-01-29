<?php
// app/Http/Controllers/Api/UsersController.php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $u = $this->requireAuth();

        // Only staff can search users (prevents applicants enumerating users)
        if (!in_array($u->role, ['admin', 'employer', 'agency'], true)) {
            return $this->fail('Forbidden', null, 403);
        }

        $role = $request->query('role'); // admin|employer|agency|applicant|null
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        $query = User::query()
            ->select(['id', 'role', 'email', 'phone', 'status', 'created_at', 'updated_at'])
            ->where('status', 'active');

        if ($role && in_array($role, ['admin', 'employer', 'agency', 'applicant'], true)) {
            $query->where('role', $role);
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                if (ctype_digit($q)) {
                    $qq->orWhere('id', (int) $q);
                }
                $qq->orWhere('email', 'like', "%{$q}%")
                   ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $rows = $query
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $display = $r->email ?: ($r->phone ?: ('User #' . $r->id));
                return [
                    'id' => (int) $r->id,
                    'role' => (string) $r->role,
                    'email' => $r->email,
                    'phone' => $r->phone,
                    'display_name' => $display, // frontend label
                ];
            })
            ->values();

        return $this->ok($rows);
    }
}