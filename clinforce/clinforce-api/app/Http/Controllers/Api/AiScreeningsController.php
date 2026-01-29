<?php
// app/Http/Controllers/Api/AiScreeningsController.php

namespace App\Http\Controllers\Api;

use App\Models\AiScreening;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;

class AiScreeningsController extends ApiController
{
    // GET /ai-screenings?application_id=...
    // Admin OR job owner OR applicant (their own application)
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        $applicationId = request()->query('application_id');
        if (!$applicationId) {
            return $this->fail('application_id is required', ['application_id' => ['Required']], 422);
        }

        $application = JobApplication::query()
            ->with('job')
            ->find($applicationId);

        if (!$application) return $this->fail('Not found', null, 404);

        $isApplicant = $application->applicant_user_id === $u->id;
        $isOwner = $application->job
            && in_array($u->role, ['employer','agency'], true)
            && $application->job->owner_user_id === $u->id
            && $application->job->owner_type === $u->role;

        if ($u->role !== 'admin' && !$isApplicant && !$isOwner) {
            return $this->fail('Forbidden', null, 403);
        }

        $rows = AiScreening::query()
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->get();

        return $this->ok($rows);
    }
}
