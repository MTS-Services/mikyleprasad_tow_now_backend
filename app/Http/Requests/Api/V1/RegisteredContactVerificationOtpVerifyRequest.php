<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisteredContactVerificationOtpVerifyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'identifier_type' => ['required', 'string', Rule::in(['email', 'phone', 'username'])],
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}
