<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\JobPublishRequest;
use App\Http\Requests\Api\JobStoreRequest;
use App\Http\Requests\Api\JobUpdateRequest;
use App\Models\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class JobsController extends Controller
{
    /**
     * GET /api/jobs
     * Owner / Admin listing (dashboard)
     */
  public function index(Request $request): JsonResponse
{
    $user = $request->user();

    $q = Job::query();

    // ✅ Candidate/Guest path: published only
    if (!$user || !in_array($user->role, ['admin', 'employer', 'agency'], true)) {
        $q->where('status', 'published')
          ->whereNotNull('published_at')
          ->orderByDesc('published_at');

        if ($request->filled('q')) {
            $term = trim((string) $request->query('q'));
            $q->where(function ($qq) use ($term) {
                $qq->where('title', 'like', "%{$term}%")
                   ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('city')) {
            $q->where('city', $request->query('city'));
        }

        if ($request->filled('employment_type')) {
            $q->where('employment_type', $request->query('employment_type'));
        }

        if ($request->filled('work_mode')) {
            $q->where('work_mode', $request->query('work_mode'));
        }

        return response()->json($q->paginate(10));
    }

    // ✅ Owner/Admin path
    $q->orderByDesc('created_at');

    if ($user->role !== 'admin') {
        $q->where('owner_type', $user->role)
          ->where('owner_user_id', $user->id);
    }

    if ($request->filled('status')) {
        $status = strtolower((string) $request->query('status'));
        if (in_array($status, ['draft', 'published', 'archived'], true)) {
            $q->where('status', $status);
        }
    }

    if ($request->filled('q')) {
        $term = trim((string) $request->query('q'));
        $q->where(function ($qq) use ($term) {
            $qq->where('title', 'like', "%{$term}%")
               ->orWhere('description', 'like', "%{$term}%")
               ->orWhere('city', 'like', "%{$term}%");
        });
    }

    return response()->json($q->paginate(10));
}

    /**
     * POST /api/jobs
     */
    public function store(JobStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $ownerType = in_array($user->role, ['employer', 'agency'], true)
            ? $user->role
            : 'employer';

        $job = Job::create([
            'owner_type'      => $ownerType,
            'owner_user_id'   => $user->id,
            'title'           => $request->title,
            'description'     => $request->description,
            'employment_type' => $request->employment_type,
            'work_mode'       => $request->work_mode,
            'country_code'    => $request->country_code,
            'city'            => $request->city,
            'status'          => 'draft',
        ]);

        return response()->json(['data' => $job], 201);
    }

    /**
     * GET /api/jobs/{job}
     */
    public function show(Request $request, Job $job): JsonResponse
    {
        $this->assertCanAccess($request->user(), $job);
        return response()->json(['data' => $job]);
    }

    /**
     * PUT /api/jobs/{job}
     */
    public function update(JobUpdateRequest $request, Job $job): JsonResponse
    {
        $job->update($request->validated());
        return response()->json(['data' => $job]);
    }

    /**
     * POST /api/jobs/{job}/publish
     */
    public function publish(JobPublishRequest $request, Job $job): JsonResponse
    {
        $this->assertCanAccess($request->user(), $job);

        $job->update([
            'status'       => 'published',
            'published_at' => $request->filled('publish_at')
                ? Carbon::parse($request->publish_at)
                : now(),
            'archived_at'  => null,
        ]);

        return response()->json(['data' => $job]);
    }

    /**
     * POST /api/jobs/{job}/archive
     */
    public function archive(Request $request, Job $job): JsonResponse
    {
        $this->assertCanAccess($request->user(), $job);

        $job->update([
            'status'      => 'archived',
            'archived_at' => now(),
        ]);

        return response()->json(['data' => $job]);
    }

    /**
     * DELETE /api/jobs/{job}
     */
    public function destroy(Request $request, Job $job): JsonResponse
    {
        $this->assertCanAccess($request->user(), $job);
        $job->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * GET /api/public/jobs
     * Candidate browsing endpoint
     */
    public function publicIndex(Request $request): JsonResponse
    {
        $q = Job::query()
            ->where('status', 'published')
            ->whereNotNull('published_at');

        if ($request->filled('q')) {
            $term = trim($request->q);
            $q->where(function ($qq) use ($term) {
                $qq->where('title', 'like', "%{$term}%")
                   ->orWhere('description', 'like', "%{$term}%");
            });
        }

        if ($request->filled('city')) {
            $q->where('city', $request->city);
        }

        if ($request->filled('employment_type')) {
            $q->where('employment_type', $request->employment_type);
        }

        if ($request->filled('work_mode')) {
            $q->where('work_mode', $request->work_mode);
        }

        $q->orderByDesc('published_at');

        return response()->json(
            $q->paginate(10)
        );
    }

    /**
     * GET /api/public/jobs/{job}
     */
    public function publicShow(Request $request, Job $job): JsonResponse
    {
        if ($job->status !== 'published') {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return response()->json(['data' => $job]);
    }

    private function assertCanAccess($user, Job $job): void
    {
        if (!$user) abort(401);

        if ($user->role === 'admin') return;

        $ownerType = in_array($user->role, ['employer', 'agency'], true)
            ? $user->role
            : null;

        if (!$ownerType) abort(403);

        if (
            $job->owner_user_id !== $user->id ||
            $job->owner_type !== $ownerType
        ) {
            abort(403);
        }
    }
}
