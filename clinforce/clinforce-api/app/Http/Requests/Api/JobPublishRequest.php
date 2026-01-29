<?php

namespace App\Http\Requests\Api;

class JobPublishRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $u = $this->user();
        return (bool)$u && in_array($u->role, ['employer','agency','admin'], true);
    }

    public function rules(): array
    {
        return [
            // optional scheduled publish time
            'publish_at' => ['nullable','date','after_or_equal:now'],
        ];
    }
}
