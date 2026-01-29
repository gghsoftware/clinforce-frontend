<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ApplicantProfileUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = $this->user(); // comes from auth:sanctum
        return (bool) $u && in_array($u->role, ['applicant', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required','string','min:2','max:80','regex:/^[\pL\s\.\-\'`]+$/u'],
            'last_name'  => ['required','string','min:2','max:80','regex:/^[\pL\s\.\-\'`]+$/u'],

            // OPTIONAL fields (align these with your DB columns)
            'headline'         => ['nullable','string','max:190'],
            'summary'          => ['nullable','string','max:5000'],
            'years_experience' => ['nullable','integer','min:0','max:80'],
            'country_code'     => ['nullable','string','size:2','regex:/^[A-Z]{2}$/'],
            'city'             => ['nullable','string','min:2','max:120'],

            // If you also send these from the Vue page, add rules here too:
            'location'            => ['nullable','string','max:190'],
            'preferred_locations' => ['nullable','string','max:500'],
            'primary_role'        => ['nullable','string','max:190'],

            // Arrays (if your applicant_profiles columns are JSON)
            'experiences' => ['nullable','array'],
            'education'   => ['nullable','array'],
            'licenses'    => ['nullable','array'],
            'skills'      => ['nullable','array'],

            'preferred_shift' => ['nullable','in:any,day,night,rotational'],
            'start_date'      => ['nullable','date'],
            'work_setup'      => ['nullable','in:onsite,hybrid,remote'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name'  => trim((string) $this->input('last_name')),
        ]);

        foreach (['headline','summary','city','location','preferred_locations','primary_role'] as $k) {
            if ($this->has($k)) $this->merge([$k => trim((string) $this->input($k))]);
        }

        if ($this->has('country_code')) {
            $this->merge(['country_code' => strtoupper(trim((string) $this->input('country_code')))]);
        }
    }
}
