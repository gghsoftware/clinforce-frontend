<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class JobStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['employer','agency','admin'], true);
    }

    public function rules(): array
    {
        return [
            'title' => ['required','string','min:5','max:190'],
            'description' => ['required','string','min:30','max:20000'],
            'employment_type' => ['required', Rule::in(['full_time','part_time','contract','temporary','internship'])],
            'work_mode' => ['required', Rule::in(['on_site','remote','hybrid'])],
            'country_code' => ['nullable','string','size:2','regex:/^[A-Z]{2}$/'],
            'city' => ['nullable','string','min:2','max:120'],
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
