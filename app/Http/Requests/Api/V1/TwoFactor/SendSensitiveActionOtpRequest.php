<?php

namespace App\Http\Requests\Api\V1\TwoFactor;

use App\Enums\OtpDeliveryPreference;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendSensitiveActionOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $authLogin = app(AuthLoginConfiguration::class);

        if (! $authLogin->bothEmailAndPhoneLoginIdentifiers()) {
            return [];
        }

        if ($authLogin->otpDeliveryPreference() !== OtpDeliveryPreference::UserChoice) {
            return [];
        }

        return [
            'delivery' => ['required', Rule::in(['email', 'phone'])],
        ];
    }
}
