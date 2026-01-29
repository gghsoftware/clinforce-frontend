<?php
// app/Http/Controllers/Api/PlansController.php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlansController extends ApiController
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->where('is_active', 1)
            ->orderBy('price_cents')
            ->get();

        return $this->ok($plans);
    }
}
