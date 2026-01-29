<?php

namespace App\Http\Requests\Api;

use App\Models\Job;
use App\Models\JobApplication;

class JobApplyRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && ($u->role === 'applicant' || $u->role === 'admin');
    }

    public function rules(): array
    {
        return [
            'cover_letter' => ['nullable','string','max:8000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $u = $this->user();
            /** @var Job|null $job */
            $job = $this->route('job');

            if (!$u || !$job) return;

            if ($u->role !== 'admin' && $u->role !== 'applicant') {
                $validator->errors()->add('role', 'Only applicants can apply.');
                return;
            }

            if ($job->status !== 'published') {
                $validator->errors()->add('job', 'Job is not open for applications.');
                return;
            }

            if ($job->owner_user_id === $u->id) {
                $validator->errors()->add('job', 'Owner cannot apply to own job.');
                return;
            }

            $dup = JobApplication::query()
                ->where('job_id', $job->id)
                ->where('applicant_user_id', $u->id)
                ->exists();

            if ($dup) {
                $validator->errors()->add('job', 'Already applied to this job.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cover_letter')) {
            $this->merge(['cover_letter' => trim((string)$this->input('cover_letter'))]);
        }
    }
}
