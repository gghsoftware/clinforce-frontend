<?php
// app/Http/Controllers/Api/HealthController.php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class HealthController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok([
            'status' => 'ok',
            'app' => 'clinforce-api',
            'time' => now()->toIso8601String(),
        ]);
    }
}
