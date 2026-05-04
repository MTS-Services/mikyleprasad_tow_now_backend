<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\LoginIdentifierType;
use App\Enums\LoginType;
use App\Http\Requests\Concerns\ValidatesOtpIdentifierRequestBody;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    use ValidatesOtpIdentifierRequestBody;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->loginModeIsOtp()) {
            $this->mergeOtpIdentifierFromDedicatedFieldWhenSingle();
        }
    }

    public function rules(): array
    {
        $usernameField = config('fortify.username', 'email');

        if ($this->loginModeIsOtp()) {
            $auth = app(AuthLoginConfiguration::class);
            $allowed = $auth->loginIdentifierTypes();
            $identifierBlock = $this->otpIdentifierInputRules();

            if (count($allowed) > 1) {
                return array_merge($identifierBlock, $this->otpSupplementContactRules(), [
                    'password' => ['sometimes', 'nullable', 'string'],
                    'identifier_type' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone', 'username'])],
                    'name' => ['sometimes', 'nullable', 'string', 'max:255'],
                    'delivery' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone'])],
                    'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
                ]);
            }

            return match ($allowed[0]) {
                LoginIdentifierType::Email => array_merge($identifierBlock, $this->otpModeSingleCommonRules()),
                LoginIdentifierType::Phone => array_merge($identifierBlock, $this->otpModeSingleCommonRules()),
                LoginIdentifierType::Username => array_merge($identifierBlock, [
                    'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
                    'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
                ], $this->otpModeSingleCommonRules()),
            };
        }

        return [
            $usernameField => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->loginModeIsOtp()) {
                $this->appendSingleIdentifierRequiredError($validator);
            }
        });
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function otpModeSingleCommonRules(): array
    {
        return [
            'password' => ['sometimes', 'nullable', 'string'],
            'identifier_type' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone', 'username'])],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone'])],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    private function loginModeIsOtp(): bool
    {
        try {
            return app(AuthLoginConfiguration::class)->loginType() === LoginType::Otp;
        } catch (\Throwable) {
            return false;
        }
    }
}
