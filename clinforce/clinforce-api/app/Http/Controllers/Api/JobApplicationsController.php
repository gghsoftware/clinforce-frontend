<?php
// app/Http/Controllers/Api/JobApplicationsController.php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\JobApplyRequest;
use App\Http\Requests\Api\JobApplicationStatusUpdateRequest;
use App\Models\ApplicationStatusHistory;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class JobApplicationsController extends ApiController
{
    /**
     * Your DB enum roles: admin, employer, agency, applicant
     * So applicant = "applicant" (no "candidate" role).
     */
    private const APPLICANT_ROLES = ['applicant', 'admin'];
    private const OWNER_ROLES     = ['employer', 'agency', 'admin'];

    private function isAdmin($u): bool
    {
        return $u && $u->role === 'admin';
    }

    private function isApplicantRole($u): bool
    {
        return $u && in_array($u->role, self::APPLICANT_ROLES, true);
    }

    private function isOwnerRole($u): bool
    {
        return $u && in_array($u->role, self::OWNER_ROLES, true);
    }

    /**
     * POST /jobs/{job}/apply
     */
    public function apply(JobApplyRequest $request, Job $job): JsonResponse
    {
        $u = $this->requireAuth();

        // Only applicant/admin can apply
        if (!$this->isApplicantRole($u)) {
            return $this->fail('Forbidden (403). Only applicant accounts can apply.', null, 403);
        }

        $v = $request->validated();

        $app = null;

        DB::transaction(function () use ($job, $u, $v, &$app) {
            $app = JobApplication::query()->create([
                'job_id'            => $job->id,
                'applicant_user_id' => $u->id,
                'status'            => 'submitted',
                'cover_letter'      => $v['cover_letter'] ?? null,
                'submitted_at'      => now(),
            ]);

            ApplicationStatusHistory::query()->create([
                'application_id'      => $app->id,
                'from_status'         => null,
                'to_status'           => 'submitted',
                'changed_by_user_id'  => $u->id,
                'note'                => 'Initial submission',
                'created_at'          => now(),
            ]);
        });

        return $this->ok($app, 'Application submitted', 201);
    }

    /**
     * GET /applications?scope=mine|owned&status=...
     *
     * - scope=mine  => applicant sees own applications (admin sees all)
     * - scope=owned => employer/agency sees applications for jobs they own (admin sees all)
     */
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        $scope = (string) request()->query('scope', 'mine');
        $scope = in_array($scope, ['mine', 'owned'], true) ? $scope : 'mine';

        $q = JobApplication::query()->with(['job']);

        if ($scope === 'owned') {
            if (!$this->isOwnerRole($u)) {
                return $this->fail('Forbidden (403). Your request is not allowed for this account/scope.', null, 403);
            }

            // employer/agency: only jobs they own
            if (!$this->isAdmin($u)) {
                $q->whereHas('job', function ($jq) use ($u) {
                    $jq->where('owner_user_id', $u->id)
                       ->where('owner_type', $u->role);
                });
            }
        } else {
            // mine
            if (!$this->isApplicantRole($u)) {
                return $this->fail('Forbidden (403). Your request is not allowed for this account/scope.', null, 403);
            }

            if (!$this->isAdmin($u)) {
                $q->where('applicant_user_id', $u->id);
            }
        }

        if ($status = request()->query('status')) {
            $allowed = ['submitted', 'shortlisted', 'rejected', 'interview', 'hired', 'withdrawn'];
            if (!in_array($status, $allowed, true)) {
                return $this->fail('Invalid status filter', ['status' => ['Invalid']], 422);
            }
            $q->where('status', $status);
        }

        return $this->ok($q->orderByDesc('id')->paginate(20));
    }

    /**
     * GET /applications/{application}
     */
    public function show(JobApplication $application): JsonResponse
    {
        $u = $this->requireAuth();

        $application->load(['job', 'statusHistory', 'interview', 'aiScreenings']);

        $isApplicant = $application->applicant_user_id === $u->id;

        $isOwner = $application->job
            && in_array($u->role, ['employer', 'agency'], true)
            && $application->job->owner_user_id === $u->id
            && $application->job->owner_type === $u->role;

        if (!$this->isAdmin($u) && !$isApplicant && !$isOwner) {
            return $this->fail('Forbidden (403). Your request is not allowed for this application.', null, 403);
        }

        return $this->ok($application);
    }

    /**
     * PATCH /applications/{application}/status
     */
    public function updateStatus(JobApplicationStatusUpdateRequest $request, JobApplication $application): JsonResponse
    {
        $u = $this->requireAuth();
        $v = $request->validated();

        $application->load('job');

        $isApplicant = $application->applicant_user_id === $u->id;

        $isOwner = $application->job
            && in_array($u->role, ['employer', 'agency'], true)
            && $application->job->owner_user_id === $u->id
            && $application->job->owner_type === $u->role;

        if (!$this->isAdmin($u) && !$isApplicant && !$isOwner) {
            return $this->fail('Forbidden (403). Your request is not allowed for this application.', null, 403);
        }

        $from = $application->status;
        $to   = $v['status'];

        if (!$this->isAdmin($u)) {
            // Applicant can only withdraw
            if ($isApplicant && $to !== 'withdrawn') {
                return $this->fail('Applicant can only withdraw', null, 422);
            }

            if ($from === 'hired') {
                return $this->fail('Cannot change status after hired', null, 409);
            }

            $allowedOwnerTransitions = [
                'submitted'   => ['shortlisted', 'rejected', 'interview'],
                'shortlisted' => ['interview', 'rejected'],
                'interview'   => ['hired', 'rejected'],
                'rejected'    => [],
                'withdrawn'   => [],
                'hired'       => [],
            ];

            if ($isOwner) {
                $allowed = $allowedOwnerTransitions[$from] ?? [];
                if (!in_array($to, $allowed, true)) {
                    return $this->fail('Invalid status transition', [
                        'status' => ["Cannot change from {$from} to {$to}"],
                    ], 422);
                }
            } else {
                // applicant withdrawing
                if (in_array($from, ['rejected', 'hired'], true)) {
                    return $this->fail('Cannot withdraw after final decision', null, 409);
                }
            }
        }

        DB::transaction(function () use ($application, $from, $to, $u, $v) {
            $application->status = $to;
            $application->save();

            ApplicationStatusHistory::query()->create([
                'application_id'      => $application->id,
                'from_status'         => $from,
                'to_status'           => $to,
                'changed_by_user_id'  => $u->id,
                'note'                => $v['note'] ?? null,
                'created_at'          => now(),
            ]);
        });

        return $this->ok($application->fresh(), 'Status updated');
    }
}
