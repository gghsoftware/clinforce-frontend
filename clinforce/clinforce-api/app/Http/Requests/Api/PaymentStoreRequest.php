<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class PaymentStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return (bool)$this->user();
    }

    public function rules(): array
    {
        $u = $this->user();

        return [
            'subscription_id' => [
                'nullable',
                'integer',
                Rule::exists('subscriptions', 'id')->when(
                    $u && $u->role !== 'admin',
                    fn ($rule) => $rule->where('user_id', $u->id)
                ),
            ],
            'provider' => ['required', Rule::in(['stripe','paypal','paymongo','gcash','cashapp','venmo','other'])],
            'provider_ref' => ['required','string','min:6','max:190'],
            'amount_cents' => ['required','integer','min:1','max:200000000'],
            'currency' => ['required','string','size:3','regex:/^[A-Z]{3}$/'],
            'status' => ['nullable', Rule::in(['pending','paid','failed','refunded'])],
            'paid_at' => ['nullable','date'],
            'raw_payload' => ['nullable','array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) $this->merge(['currency' => strtoupper(trim((string)$this->input('currency')))]);
        if ($this->has('provider_ref')) $this->merge(['provider_ref' => trim((string)$this->input('provider_ref'))]);
    }
}
