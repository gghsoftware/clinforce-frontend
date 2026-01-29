<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ], $code);
    }

    protected function fail(string $message, mixed $errors = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Prefer real authenticated user (Sanctum) ALWAYS.
     * Local dev can optionally impersonate via X-User-Id (only if NOT authenticated).
     */
    protected function requireAuth(?Request $request = null): User
    {
        $request = $request ?: request();

        // ✅ 1) If Sanctum authenticated, always use it (even in local)
        $authUser = $request->user(); // works with auth:sanctum middleware
        if ($authUser instanceof User) return $authUser;

        // ✅ 2) Fallback to Auth::user() (in case you use another guard somewhere)
        $authUser2 = Auth::user();
        if ($authUser2 instanceof User) return $authUser2;

        // ✅ 3) Local-only impersonation (only if NOT authenticated)
        if (app()->environment('local')) {
            $asUserId = $request->header('X-User-Id');
            if ($asUserId) {
                $u = User::query()->find($asUserId);
                if ($u instanceof User) return $u;
            }
        }

        abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated');
    }
}
