<?php

namespace App\Http\Requests\Api;

class AgencyProfileUpsertRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['agency','admin'], true);
    }

    public function rules(): array
    {
        return [
            'agency_name' => ['required','string','min:2','max:190'],
            'country_code' => ['nullable','string','size:2','regex:/^[A-Z]{2}$/'],
            'city' => ['nullable','string','min:2','max:120'],
            'address_line' => ['nullable','string','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['agency_name','city','address_line'] as $k) {
            if ($this->has($k)) $this->merge([$k => trim((string)$this->input($k))]);
        }
        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string)$this->input('country_code')))]);
        }
    }
}
