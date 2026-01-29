<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class EmployerProfileUpsertRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['employer','admin'], true);
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required','string','min:2','max:190'],
            'business_type' => ['required', Rule::in(['clinic','hospital','medical_agency','other'])],
            'country_code' => ['nullable','string','size:2','regex:/^[A-Z]{2}$/'],
            'city' => ['nullable','string','min:2','max:120'],
            'address_line' => ['nullable','string','max:255'],
            'website_url' => ['nullable','url','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['business_name','city','address_line','website_url'] as $k) {
            if ($this->has($k)) $this->merge([$k => trim((string)$this->input($k))]);
        }
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string)$this->input('country_code')))]);
        }
    }
}
