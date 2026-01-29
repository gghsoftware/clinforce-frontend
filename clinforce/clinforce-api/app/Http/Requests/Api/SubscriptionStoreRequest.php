<?php

namespace App\Http\Requests\Api;

class SubscriptionStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['employer','agency','admin'], true);
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required','integer','exists:plans,id'],
            'start_at' => ['nullable','date','after_or_equal:now'],
        ];
    }
}
