<?php
// app/Http/Controllers/Api/SubscriptionsController.php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SubscriptionStoreRequest;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SubscriptionsController extends ApiController
{
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        $q = Subscription::query()->with('plan')->orderByDesc('id');

        if ($u->role !== 'admin') {
            if (!in_array($u->role, ['employer','agency'], true)) {
                return $this->fail('Only employer/agency can manage subscriptions', null, 403);
            }
            $q->where('user_id', $u->id);
        }

        return $this->ok($q->paginate(20));
    }

    public function store(SubscriptionStoreRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'admin' && !in_array($u->role, ['employer','agency'], true)) {
            return $this->fail('Only employer/agency can subscribe', null, 403);
        }

        $v = $request->validated();

        $plan = Plan::query()->where('id', $v['plan_id'])->where('is_active', 1)->first();
        if (!$plan) return $this->fail('Invalid plan', ['plan_id' => ['Not active or not found']], 422);

        if ($u->role !== 'admin') {
            $overlap = Subscription::query()
                ->where('user_id', $u->id)
                ->whereIn('status', ['active','past_due'])
                ->where('end_at', '>', now())
                ->exists();

            if ($overlap) return $this->fail('Existing active subscription detected', null, 409);
        }

        $start = isset($v['start_at']) ? now()->parse($v['start_at']) : now();
        $end = (clone $start)->addMonths((int) $plan->duration_months);

        $sub = null;
        DB::transaction(function () use (&$sub, $u, $plan, $start, $end) {
            $sub = Subscription::query()->create([
                'user_id' => $u->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'start_at' => $start,
                'end_at' => $end,
            ]);
        });

        return $this->ok($sub->load('plan'), 'Subscription created', 201);
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'admin' && $subscription->user_id !== $u->id) {
            return $this->fail('Forbidden', null, 403);
        }

        if (in_array($subscription->status, ['cancelled','expired'], true)) {
            return $this->ok($subscription, 'Already inactive');
        }

        $subscription->status = 'cancelled';
        $subscription->cancelled_at = now();
        $subscription->save();

        return $this->ok($subscription, 'Cancelled');
    }
}
