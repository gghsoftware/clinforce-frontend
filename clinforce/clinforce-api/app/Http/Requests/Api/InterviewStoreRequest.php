<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class InterviewStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'scheduled_start' => ['required', 'date', 'after:now'],
            'scheduled_end'   => ['required', 'date', 'after:scheduled_start'],
            'mode'            => ['required', Rule::in(['in_person', 'video', 'phone'])],

            // IMPORTANT:
            // meeting_link is OPTIONAL in validation (because backend can auto-generate it)
            'meeting_link'    => ['nullable', 'string', 'max:255'],

            // location_text optional in validation (controller enforces it when mode=in_person)
            'location_text'   => ['nullable', 'string', 'max:255'],
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