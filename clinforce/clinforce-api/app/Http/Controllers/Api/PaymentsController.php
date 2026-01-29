<?php
// app/Http/Controllers/Api/PaymentsController.php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PaymentStoreRequest;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentsController extends ApiController
{
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        $q = Payment::query()->orderByDesc('id');
        if ($u->role !== 'admin') $q->where('user_id', $u->id);

        return $this->ok($q->paginate(30));
    }

    public function store(PaymentStoreRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        $v = $request->validated();

        // Request already guarantees subscription ownership (Rule::exists with where user_id), unless admin.
        // Prevent duplicate provider ref (extra safety)
        $exists = Payment::query()
            ->where('provider', $v['provider'])
            ->where('provider_ref', $v['provider_ref'])
            ->exists();

        if ($exists) return $this->fail('Duplicate payment reference', null, 409);

        $status = $v['status'] ?? 'pending';
        $paidAt = $status === 'paid' ? ($v['paid_at'] ?? now()) : null;

        $payment = Payment::query()->create([
            'user_id' => $u->id,
            'subscription_id' => $v['subscription_id'] ?? null,
            'provider' => $v['provider'],
            'provider_ref' => $v['provider_ref'],
            'amount_cents' => $v['amount_cents'],
            'currency' => $v['currency'],
            'status' => $status,
            'paid_at' => $paidAt,
            'raw_payload' => $v['raw_payload'] ?? null,
            'created_at' => now(),
        ]);

        return $this->ok($payment, 'Payment recorded', 201);
    }
}
