<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class VerificationReviewRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && $u->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['approved','rejected'])],
            'notes' => ['nullable','string','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes')) $this->merge(['notes' => trim((string)$this->input('notes'))]);
    }
}
