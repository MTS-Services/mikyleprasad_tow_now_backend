<?php

namespace App\Http\Requests\Api\V1\Otp;

use App\Http\Requests\Concerns\ValidatesOtpIdentifierRequestBody;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginOtpVerifyRequest extends FormRequest
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
        return array_merge($this->otpIdentifierInputRules(), [
            'identifier_type' => ['sometimes', 'nullable', 'string', Rule::in(['email', 'phone', 'username'])],
            'code' => ['required', 'string', 'max:32'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->appendSingleIdentifierRequiredError($validator);
        });
    }
}
