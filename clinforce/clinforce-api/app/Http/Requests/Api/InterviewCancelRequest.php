<?php

namespace App\Http\Requests\Api;

class InterviewCancelRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool)$this->user();
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['nullable','string','max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cancel_reason')) {
            $this->merge(['cancel_reason' => trim((string)$this->input('cancel_reason'))]);
        }
    }
}
