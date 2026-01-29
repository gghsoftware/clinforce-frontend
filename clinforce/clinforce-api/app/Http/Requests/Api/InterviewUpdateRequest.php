<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class InterviewUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'scheduled_start' => ['sometimes', 'required', 'date', 'after:now'],
            'scheduled_end'   => ['sometimes', 'required', 'date', 'after:scheduled_start'],
            'mode'            => ['sometimes', 'required', Rule::in(['in_person', 'video', 'phone'])],

            // OPTIONAL (controller enforces requirements)
            'meeting_link'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'location_text'   => ['sometimes', 'nullable', 'string', 'max:255'],

            'status'          => ['sometimes', 'required', Rule::in(['proposed','confirmed','rescheduled','completed','cancelled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['meeting_link', 'location_text'] as $k) {
            if ($this->has($k)) {
                $v = trim((string) $this->input($k));
                $this->merge([$k => $v === '' ? null : $v]);
            }
        }
    }
}