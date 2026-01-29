<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;
use App\Models\Job;

class JobUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        if (!$u) return false;

        // admin can edit anything
        if ($u->role === 'admin') return true;

        // only employer/agency can edit jobs
        if (!in_array($u->role, ['employer','agency'], true)) return false;

        // route('job') might be a Job model or an id
        $jobParam = $this->route('job');

        /** @var Job|null $job */
        $job = null;

        if ($jobParam instanceof Job) {
            $job = $jobParam;
        } else {
            $jobId = is_scalar($jobParam) ? (int) $jobParam : 0;
            if ($jobId > 0) $job = Job::query()->find($jobId);
        }

        if (!$job) return false;

        // IMPORTANT: owner_type is 'employer' or 'agency'
        return ((int)$job->owner_user_id === (int)$u->id) && ((string)$job->owner_type === (string)$u->role);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes','required','string','min:5','max:190'],
            'description' => ['sometimes','required','string','min:30','max:20000'],
            'employment_type' => ['sometimes','required', Rule::in(['full_time','part_time','contract','temporary','internship'])],
            'work_mode' => ['sometimes','required', Rule::in(['on_site','remote','hybrid'])],
            'country_code' => ['sometimes','nullable','string','size:2','regex:/^[A-Z]{2}$/'],
            'city' => ['sometimes','nullable','string','min:2','max:120'],

            // forbid sensitive fields
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'archived_at' => ['prohibited'],
            'owner_type' => ['prohibited'],
            'owner_user_id' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }
        if ($this->has('city')) {
            $this->merge(['city' => trim((string) $this->input('city'))]);
        }
        if ($this->has('title')) {
            $this->merge(['title' => trim((string) $this->input('title'))]);
        }
    }

    public function messages(): array
    {
        return [
            'country_code.regex' => 'country_code must be ISO-2 uppercase (e.g., PH, US).'
        ];
    }
}
