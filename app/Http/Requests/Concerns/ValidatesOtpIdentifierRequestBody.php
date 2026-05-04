<?php

namespace App\Http\Requests\Concerns;

use App\Enums\LoginIdentifierType;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesOtpIdentifierRequestBody
{
    /**
     * When only one login identifier is configured, copy `email`, `phone`, or `username`
     * into `identifier` so downstream OTP actions stay unchanged.
     */
    protected function mergeOtpIdentifierFromDedicatedFieldWhenSingle(): void
    {
        $auth = app(AuthLoginConfiguration::class);
        $allowed = $auth->loginIdentifierTypes();
        if (count($allowed) !== 1) {
            return;
        }

        $id = $this->input('identifier');
        if (is_string($id) && trim($id) !== '') {
            return;
        }

        $only = $allowed[0];
        $fallback = match ($only) {
            LoginIdentifierType::Email => $this->input('email'),
            LoginIdentifierType::Phone => $this->input('phone'),
            LoginIdentifierType::Username => $this->input('username'),
        };

        if (is_string($fallback) && trim($fallback) !== '') {
            $this->merge(['identifier' => trim($fallback)]);
        }
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function otpIdentifierInputRules(): array
    {
        $auth = app(AuthLoginConfiguration::class);
        $allowed = $auth->loginIdentifierTypes();

        if (count($allowed) > 1) {
            return [
                'identifier' => ['required', 'string', 'max:255'],
            ];
        }

        return match ($allowed[0]) {
            LoginIdentifierType::Email => [
                'identifier' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'string', 'email', 'max:255'],
            ],
            LoginIdentifierType::Phone => [
                'identifier' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:32'],
            ],
            LoginIdentifierType::Username => [
                'identifier' => ['nullable', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255'],
            ],
        };
    }

    protected function appendSingleIdentifierRequiredError(Validator $validator): void
    {
        $auth = app(AuthLoginConfiguration::class);
        $allowed = $auth->loginIdentifierTypes();

        if (count($allowed) !== 1) {
            return;
        }

        $id = $this->input('identifier');
        if (is_string($id) && trim($id) !== '') {
            return;
        }

        $field = match ($allowed[0]) {
            LoginIdentifierType::Email => 'email',
            LoginIdentifierType::Phone => 'phone',
            LoginIdentifierType::Username => 'username',
        };

        $validator->errors()->add(
            $field,
            __('validation.required', ['attribute' => $this->otpDedicatedFieldDisplayName($field)])
        );
    }

    protected function otpDedicatedFieldDisplayName(string $field): string
    {
        $custom = trans('validation.attributes.'.$field);

        return $custom !== 'validation.attributes.'.$field ? $custom : $field;
    }

    /**
     * Optional supplement rules when multiple identifier kinds are allowed.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function otpSupplementContactRules(): array
    {
        return [
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
