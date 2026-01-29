<?php
// app/Http/Controllers/Api/VerificationRequestsController.php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VerificationRequestStoreRequest;
use App\Http\Requests\Api\VerificationReviewRequest;
use App\Models\AgencyProfile;
use App\Models\EmployerProfile;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VerificationRequestsController extends ApiController
{
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        $q = VerificationRequest::query()->orderByDesc('id');
        if ($u->role !== 'admin') $q->where('user_id', $u->id);

        return $this->ok($q->paginate(20));
    }

    public function store(VerificationRequestStoreRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        $v = $request->validated();

        if (!in_array($u->role, ['employer','agency'], true)) {
            return $this->fail('Only employer/agency can request verification', null, 403);
        }
        if ($v['role'] !== $u->role) {
            return $this->fail('Role mismatch', ['role' => ['Must match your account role']], 422);
        }

        $pending = VerificationRequest::query()
            ->where('user_id', $u->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) return $this->fail('Existing pending request detected', null, 409);

        $vr = null;

        DB::transaction(function () use (&$vr, $u, $v) {
            $vr = VerificationRequest::query()->create([
                'user_id' => $u->id,
                'role' => $u->role,
                'status' => 'pending',
                'notes' => $v['notes'] ?? null,
                'created_at' => now(),
            ]);

            if ($u->role === 'employer') {
                EmployerProfile::query()->where('user_id', $u->id)->update(['verification_status' => 'pending']);
            } else {
                AgencyProfile::query()->where('user_id', $u->id)->update(['verification_status' => 'pending']);
            }
        });

        return $this->ok($vr, 'Verification request created', 201);
    }

    // Route model binding should be: {verificationRequest}
    public function review(VerificationReviewRequest $request, VerificationRequest $verificationRequest): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'admin') return $this->fail('Admin only', null, 403);

        if ($verificationRequest->status !== 'pending') {
            return $this->fail('Request already reviewed', null, 409);
        }

        $v = $request->validated();

        DB::transaction(function () use ($verificationRequest, $u, $v) {
            $verificationRequest->status = $v['status'];
            $verificationRequest->reviewed_by_user_id = $u->id;
            $verificationRequest->reviewed_at = now();
            $verificationRequest->notes = $v['notes'] ?? $verificationRequest->notes;
            $verificationRequest->save();

            $approve = $v['status'] === 'approved';

            if ($verificationRequest->role === 'employer') {
                EmployerProfile::query()->where('user_id', $verificationRequest->user_id)->update([
                    'verification_status' => $approve ? 'verified' : 'rejected',
                    'verified_at' => $approve ? now() : null,
                    'rejected_reason' => $approve ? null : ($v['notes'] ?? 'Rejected'),
                ]);
            } else {
                AgencyProfile::query()->where('user_id', $verificationRequest->user_id)->update([
                    'verification_status' => $approve ? 'verified' : 'rejected',
                    'verified_at' => $approve ? now() : null,
                    'rejected_reason' => $approve ? null : ($v['notes'] ?? 'Rejected'),
                ]);
            }
        });

        return $this->ok($verificationRequest->fresh(), 'Reviewed');
    }
}
