<?php

namespace App\Http\Requests\Api\V1\TwoFactor;

use App\Enums\LoginType;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Foundation\Http\FormRequest;

class TwoFactorSensitiveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('otp') && ! is_string($this->input('otp'))) {
            $this->merge([
                'otp' => (string) $this->input('otp'),
            ]);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $authLogin = app(AuthLoginConfiguration::class);

        if ($authLogin->loginType() === LoginType::Password) {
            return [
                'password' => ['required', 'string', 'current_password:api'],
                'otp' => ['prohibited'],
            ];
        }

        return [
            'otp' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                'size:'.$authLogin->otpCodeLength(),
            ],
            'password' => ['prohibited'],
        ];
    }
}
