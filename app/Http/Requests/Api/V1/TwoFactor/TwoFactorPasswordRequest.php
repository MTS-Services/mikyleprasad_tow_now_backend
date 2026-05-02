<?php

namespace App\Http\Requests\Api\V1\TwoFactor;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'current_password:api'],
        ];
    }
}
