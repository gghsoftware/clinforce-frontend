<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class JobApplicationStatusUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool)$this->user();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['submitted','shortlisted','rejected','interview','hired','withdrawn'])],
            'note' => ['nullable','string','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('note')) {
            $this->merge(['note' => trim((string)$this->input('note'))]);
        }
    }
}
