<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class VerificationRequestStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['employer','agency','admin'], true);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(['employer','agency'])],
            'notes' => ['nullable','string','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) $this->merge(['notes' => trim((string)$this->input('notes'))]);
    }
}
