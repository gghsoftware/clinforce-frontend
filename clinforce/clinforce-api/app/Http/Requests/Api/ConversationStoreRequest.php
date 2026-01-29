<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class ConversationStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable','string','max:190'],
            'participant_user_ids' => ['required','array','min:1','max:20'],
            'participant_user_ids.*' => ['required','integer','distinct','exists:users,id'],
            'first_message' => ['required','string','min:1','max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('subject')) {
            $this->merge(['subject' => trim((string)$this->input('subject'))]);
        }
        if ($this->has('first_message')) {
            $this->merge(['first_message' => trim((string)$this->input('first_message'))]);
        }
    }
}