<?php

namespace App\Http\Requests\Api;

class MessageStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'body' => ['required','string','min:1','max:5000'],
            'attachments_json' => ['nullable','array'], // optional
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('body')) {
            $this->merge(['body' => trim((string)$this->input('body'))]);
        }
    }
}