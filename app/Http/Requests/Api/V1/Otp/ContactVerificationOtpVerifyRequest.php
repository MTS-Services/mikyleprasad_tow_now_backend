<?php

namespace App\Http\Requests\Api\V1\Otp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactVerificationOtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', Rule::in(['email', 'phone'])],
            'code' => ['required', 'string', 'max:32'],
        ];
    }
}
