<?php

namespace App\Http\Requests\Api;

class ConversationMarkReadRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'last_read_message_id' => ['required','integer','min:1'],
        ];
    }
}