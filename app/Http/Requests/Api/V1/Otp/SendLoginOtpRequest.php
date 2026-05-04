<?php

namespace App\Http\Requests\Api\V1\Otp;

use App\Enums\LoginIdentifierType;
use App\Http\Requests\Concerns\ValidatesOtpIdentifierRequestBody;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendLoginOtpRequest extends FormRequest
{
    use ValidatesOtpIdentifierRequestBody;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeOtpIdentifierFromDedicatedFieldWhenSingle();
    }

    public function rules(): array
    {
        $auth = app(AuthLoginConfiguration::class);
        $allowed = $auth->loginIdentifierTypes();
        $identifierBlock = $this->otpIdentifierInputRules();

        if (count($allowed) > 1) {
            return array_merge($identifierBlock, $this->otpSupplementContactRules(), $this->otpCommonMetaRules());
        }

        return match ($allowed[0]) {
            LoginIdentifierType::Email => array_merge($identifierBlock, $this->otpCommonMetaRules()),
            LoginIdentifierType::Phone => array_merge($identifierBlock, $this->otpCommonMetaRules()),
            LoginIdentifierType::Username => array_merge($identifierBlock, $this->otpUsernameExtraContactRules(), $this->otpCommonMetaRules()),
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->appendSingleIdentifierRequiredError($validator);
        });
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function otpCommonMetaRules(): array
    {
        return [
            'identifier_type' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone', 'username'])],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone'])],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Extra contact fields when the only login identifier is username.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function otpUsernameExtraContactRules(): array
    {
        return [
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
