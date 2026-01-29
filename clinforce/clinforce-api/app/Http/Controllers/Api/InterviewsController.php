<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\InterviewCancelRequest;
use App\Http\Requests\Api\InterviewStoreRequest;
use App\Http\Requests\Api\InterviewUpdateRequest;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;

class InterviewsController extends ApiController
{
    public function index(): JsonResponse
    {
        $u = $this->requireAuth();

        // Always eager-load what both sides need
        $q = Interview::query()->with(['application.job']);

        if ($u->role === 'admin') {
            // no extra filters
        } elseif (in_array($u->role, ['employer','agency'], true)) {
            // Employer/agency: interviews only for jobs they own (KEEP existing behavior)
            $q->whereHas('application.job', function ($qq) use ($u) {
                $qq->where('owner_user_id', $u->id)
                   ->where('owner_type', $u->role);
            });
        } elseif ($u->role === 'applicant') {
            // Candidate/applicant: interviews only for their applications
            $q->whereHas('application', function ($qq) use ($u) {
                $qq->where('applicant_user_id', $u->id);
            });
        } else {
            return $this->fail('Forbidden', null, 403);
        }

        return $this->ok($q->orderBy('scheduled_start')->get());
    }

    public function store(InterviewStoreRequest $request, JobApplication $application): JsonResponse
    {
        $u = $this->requireAuth();

        $application->load('job');

        $isOwner = $application->job
            && in_array($u->role, ['employer','agency'], true)
            && $application->job->owner_user_id === $u->id
            && $application->job->owner_type === $u->role;

        if ($u->role !== 'admin' && !$isOwner) {
            return $this->fail('Only job owner can schedule interview', null, 403);
        }

        if (in_array($application->status, ['rejected','withdrawn'], true)) {
            return $this->fail('Cannot schedule interview for closed application', null, 409);
        }

        if (Interview::query()->where('application_id', $application->id)->exists()) {
            return $this->fail('Interview already exists for this application', null, 409);
        }

        $v = $request->validated();

        $meetingLink = $v['meeting_link'] ?? null;

        // If video and no meeting_link, auto-create Zoom meeting
        if (($v['mode'] ?? null) === 'video' && !$meetingLink) {
            if (!$this->zoomEnabled()) {
                return $this->fail(
                    'Zoom is not configured. Set ZOOM_ACCOUNT_ID / ZOOM_CLIENT_ID / ZOOM_CLIENT_SECRET in .env',
                    null,
                    422
                );
            }

            $start = Carbon::parse($v['scheduled_start']);
            $end = Carbon::parse($v['scheduled_end']);
            $topic = 'Interview • ' . ($application->job?->title ?: ('Application #' . $application->id));

            $created = $this->zoomCreateMeeting($topic, $start, $end);

            if (!$created['ok'] || empty($created['join_url'])) {
                return $this->fail('Zoom meeting create failed', $created, 422);
            }

            $meetingLink = $created['join_url'];
        }

        // in_person requires location_text
        if (($v['mode'] ?? null) === 'in_person' && empty($v['location_text'])) {
            return $this->fail('location_text required for in_person mode', ['location_text' => ['Required']], 422);
        }

        // final safety: video must end up with meeting_link
        if (($v['mode'] ?? null) === 'video' && !$meetingLink) {
            return $this->fail('meeting_link required for video mode', ['meeting_link' => ['Required']], 422);
        }

        $interview = null;

        DB::transaction(function () use (&$interview, $application, $u, $v, $meetingLink) {
            $interview = Interview::query()->create([
                'application_id' => $application->id,
                'scheduled_start' => $v['scheduled_start'],
                'scheduled_end' => $v['scheduled_end'],
                'mode' => $v['mode'],
                'meeting_link' => $meetingLink,
                'location_text' => $v['location_text'] ?? null,
                'status' => 'proposed',
                'cancel_reason' => null,
                'created_by_user_id' => $u->id,
            ]);
        });

        return $this->ok($interview, 'Interview scheduled', 201);
    }

    public function update(InterviewUpdateRequest $request, Interview $interview): JsonResponse
    {
        $u = $this->requireAuth();

        $interview->load('application.job');
        $job = $interview->application?->job;

        $isOwner = $job
            && in_array($u->role, ['employer','agency'], true)
            && $job->owner_user_id === $u->id
            && $job->owner_type === $u->role;

        if ($u->role !== 'admin' && !$isOwner) {
            return $this->fail('Forbidden', null, 403);
        }

        if (in_array($interview->status, ['cancelled','completed'], true)) {
            return $this->fail('Cannot edit cancelled/completed interview', null, 409);
        }

        $v = $request->validated();

        $mode = $v['mode'] ?? $interview->mode;

        $meetingLink = array_key_exists('meeting_link', $v)
            ? ($v['meeting_link'] ?? null)
            : $interview->meeting_link;

        $loc = array_key_exists('location_text', $v)
            ? ($v['location_text'] ?? null)
            : $interview->location_text;

        $scheduleChanged = array_key_exists('scheduled_start', $v) || array_key_exists('scheduled_end', $v);

        if ($mode === 'in_person' && !$loc) {
            return $this->fail('location_text required for in_person mode', ['location_text' => ['Required']], 422);
        }

        // If video and meeting_link is empty, re-generate Zoom meeting
        if ($mode === 'video' && !$meetingLink) {
            if (!$this->zoomEnabled()) {
                return $this->fail(
                    'Zoom is not configured. Set ZOOM_ACCOUNT_ID / ZOOM_CLIENT_ID / ZOOM_CLIENT_SECRET in .env',
                    null,
                    422
                );
            }

            $start = Carbon::parse($v['scheduled_start'] ?? $interview->scheduled_start);
            $end = Carbon::parse($v['scheduled_end'] ?? $interview->scheduled_end);
            $topic = 'Interview • ' . ($job?->title ?: ('Application #' . ($interview->application_id ?: '')));

            $created = $this->zoomCreateMeeting($topic, $start, $end);
            if (!$created['ok'] || empty($created['join_url'])) {
                return $this->fail('Zoom meeting create failed', $created, 422);
            }

            $meetingLink = $created['join_url'];
        }

        if ($mode === 'video' && !$meetingLink) {
            return $this->fail('meeting_link required for video mode', ['meeting_link' => ['Required']], 422);
        }

        DB::transaction(function () use ($interview, $v, $mode, $meetingLink, $scheduleChanged) {
            $interview->fill($v);
            $interview->mode = $mode;
            $interview->meeting_link = $meetingLink;

            if ($scheduleChanged && !in_array($interview->status, ['confirmed','completed'], true)) {
                $interview->status = 'rescheduled';
            }

            $interview->save();
        });

        return $this->ok($interview->fresh(), 'Updated');
    }

    public function cancel(InterviewCancelRequest $request, Interview $interview): JsonResponse
    {
        $u = $this->requireAuth();

        $interview->load('application.job');
        $job = $interview->application?->job;

        $isOwner = $job
            && in_array($u->role, ['employer','agency'], true)
            && $job->owner_user_id === $u->id
            && $job->owner_type === $u->role;

        $isApplicant = $interview->application && $interview->application->applicant_user_id === $u->id;

        if ($u->role !== 'admin' && !$isOwner && !$isApplicant) {
            return $this->fail('Forbidden', null, 403);
        }

        if ($interview->status === 'cancelled') return $this->ok($interview, 'Already cancelled');
        if ($interview->status === 'completed') return $this->fail('Completed interview cannot be cancelled', null, 409);

        $v = $request->validated();

        $interview->status = 'cancelled';
        $interview->cancel_reason = $v['cancel_reason'] ?? null;
        $interview->save();

        return $this->ok($interview, 'Cancelled');
    }

    /* =========================
       Zoom helpers (NO service)
       ========================= */

    private function zoomEnabled(): bool
    {
        return (bool) config('services.zoom.account_id')
            && (bool) config('services.zoom.client_id')
            && (bool) config('services.zoom.client_secret');
    }

    private function zoomAccessToken(): string
    {
        return Cache::remember('zoom_s2s_access_token', 50 * 60, function () {
            $accountId = (string) config('services.zoom.account_id');
            $clientId = (string) config('services.zoom.client_id');
            $clientSecret = (string) config('services.zoom.client_secret');

            $basic = base64_encode($clientId . ':' . $clientSecret);

            $res = Http::withHeaders([
                    'Authorization' => "Basic {$basic}",
                ])
                ->asForm()
                ->post('https://zoom.us/oauth/token', [
                    'grant_type' => 'account_credentials',
                    'account_id' => $accountId,
                ]);

            if (!$res->successful()) {
                throw new \RuntimeException('Zoom token failed: ' . $res->body());
            }

            $j = $res->json();
            $token = (string) ($j['access_token'] ?? '');
            if (!$token) throw new \RuntimeException('Zoom token missing');

            return $token;
        });
    }

    private function zoomCreateMeeting(string $topic, Carbon $start, Carbon $end): array
    {
        $token = $this->zoomAccessToken();

        $userId = (string) config('services.zoom.user_id', 'me');
        $tz = (string) config('services.zoom.timezone', 'Asia/Manila');

        $duration = max(15, $start->diffInMinutes($end));

        $payload = [
            'topic' => $topic ?: ('Interview ' . Str::upper(Str::random(6))),
            'type' => 2,
            'start_time' => $start->toIso8601String(),
            'duration' => $duration,
            'timezone' => $tz,
            'settings' => [
                'join_before_host' => false,
                'waiting_room' => true,
                'host_video' => true,
                'participant_video' => true,
                'mute_upon_entry' => true,
            ],
        ];

        $res = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post("https://api.zoom.us/v2/users/{$userId}/meetings", $payload);

        if (!$res->successful()) {
            return [
                'ok' => false,
                'status' => $res->status(),
                'error' => $res->json() ?: $res->body(),
            ];
        }

        $j = $res->json();

        return [
            'ok' => true,
            'id' => (string)($j['id'] ?? ''),
            'join_url' => (string)($j['join_url'] ?? ''),
            'start_url' => (string)($j['start_url'] ?? ''),
            'raw' => $j,
        ];
    }
}