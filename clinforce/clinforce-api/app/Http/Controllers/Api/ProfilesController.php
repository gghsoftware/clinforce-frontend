<?php
// app/Http/Controllers/Api/ProfilesController.php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AgencyProfileUpsertRequest;
use App\Http\Requests\Api\ApplicantProfileUpsertRequest;
use App\Http\Requests\Api\EmployerProfileUpsertRequest;
use App\Models\AgencyProfile;
use App\Models\ApplicantProfile;
use App\Models\EmployerProfile;
use Illuminate\Http\JsonResponse;

class ProfilesController extends ApiController
{
    public function me(): JsonResponse
    {
        $u = $this->requireAuth();
        $u->load(['applicantProfile', 'employerProfile', 'agencyProfile']);
        return $this->ok($u);
    }

    public function upsertApplicant(ApplicantProfileUpsertRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'applicant') return $this->fail('Only applicants can update applicant profile', null, 403);

        $v = $request->validated();
        $public = $v['first_name'] . ' ' . mb_substr($v['last_name'], 0, 1) . '.';

        $profile = ApplicantProfile::query()->updateOrCreate(
            ['user_id' => $u->id],
            array_merge($v, ['public_display_name' => $public])
        );

        return $this->ok($profile, 'Profile saved');
    }

    public function upsertEmployer(EmployerProfileUpsertRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'employer') return $this->fail('Only employers can update employer profile', null, 403);

        $profile = EmployerProfile::query()->updateOrCreate(
            ['user_id' => $u->id],
            $request->validated()
        );

        return $this->ok($profile, 'Profile saved');
    }

    public function upsertAgency(AgencyProfileUpsertRequest $request): JsonResponse
    {
        $u = $this->requireAuth();
        if ($u->role !== 'agency') return $this->fail('Only agencies can update agency profile', null, 403);

        $profile = AgencyProfile::query()->updateOrCreate(
            ['user_id' => $u->id],
            $request->validated()
        );

        return $this->ok($profile, 'Profile saved');
    }
}
