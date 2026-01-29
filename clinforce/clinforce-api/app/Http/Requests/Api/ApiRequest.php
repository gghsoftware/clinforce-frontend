<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ApiRequest extends FormRequest
{
    /**
     * Always treat this request as an API request (JSON).
     * Helps when Accept header isn't set correctly.
     */
    public function expectsJson(): bool
    {
        return true;
    }

    /**
     * Return JSON validation errors.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422);

        throw new ValidationException($validator, $response);
    }

    /**
     * Return JSON when authorize() fails (403).
     */
    protected function failedAuthorization(): void
    {
        throw new HttpException(403, 'This action is unauthorized.');
    }
}
