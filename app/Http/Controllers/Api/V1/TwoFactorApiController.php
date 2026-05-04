<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Otp\RequestSensitiveActionOtpAction;
use App\Actions\Api\V1\Otp\VerifySensitiveActionOtpAction;
use App\Enums\LoginType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TwoFactor\SendSensitiveActionOtpRequest;
use App\Http\Requests\Api\V1\TwoFactor\TwoFactorConfirmRequest;
use App\Http\Requests\Api\V1\TwoFactor\TwoFactorSensitiveActionRequest;
use App\Models\User;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class TwoFactorApiController extends Controller
{
    public function __construct(
        protected AuthLoginConfiguration $authLogin,
        protected VerifySensitiveActionOtpAction $verifySensitiveActionOtpAction,
    ) {}

    public function sendReauthenticationOtp(SendSensitiveActionOtpRequest $request, RequestSensitiveActionOtpAction $action): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return $action->handle($request, $user);
    }

    public function enable(TwoFactorSensitiveActionRequest $request, EnableTwoFactorAuthentication $enableTwoFactorAuthentication): JsonResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return sendResponse(
                status: false,
                message: __('api.two_factor_already_enabled'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($response = $this->verifySensitiveStepUpIfNeeded($request)) {
            return $response;
        }

        $enableTwoFactorAuthentication($user);

        return sendResponse(
            status: true,
            message: __('api.two_factor_enabled'),
            data: [
                'qr_code_svg' => $user->fresh()->twoFactorQrCodeSvg(),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return sendResponse(
                status: false,
                message: __('api.two_factor_not_started'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return sendResponse(
            status: true,
            message: __('api.qr_code'),
            data: ['qr_code_svg' => $user->twoFactorQrCodeSvg()],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function confirm(TwoFactorConfirmRequest $request, ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): JsonResponse
    {
        $user = $request->user();

        try {
            $confirmTwoFactorAuthentication($user, $request->validated('code'));
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('api.validation_error');

            return sendResponse(
                status: false,
                message: $message,
                data: ['errors' => $e->errors()],
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return sendResponse(
            status: true,
            message: __('api.two_factor_confirmed'),
            data: [
                'recovery_codes' => $user->fresh()->recoveryCodes(),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return sendResponse(
                status: false,
                message: __('api.two_factor_not_enabled'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return sendResponse(
            status: true,
            message: __('api.recovery_codes'),
            data: ['recovery_codes' => $user->recoveryCodes()],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function regenerateRecoveryCodes(TwoFactorSensitiveActionRequest $request, GenerateNewRecoveryCodes $generateNewRecoveryCodes): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return sendResponse(
                status: false,
                message: __('api.two_factor_not_enabled'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($response = $this->verifySensitiveStepUpIfNeeded($request)) {
            return $response;
        }

        $generateNewRecoveryCodes($user);

        return sendResponse(
            status: true,
            message: __('api.two_factor_recovery_regenerated'),
            data: ['recovery_codes' => $user->fresh()->recoveryCodes()],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function disable(TwoFactorSensitiveActionRequest $request, DisableTwoFactorAuthentication $disableTwoFactorAuthentication): JsonResponse
    {
        $user = $request->user();

        if ($response = $this->verifySensitiveStepUpIfNeeded($request)) {
            return $response;
        }

        $disableTwoFactorAuthentication($user);

        return sendResponse(
            status: true,
            message: __('api.two_factor_disabled'),
            data: null,
            statusCode: HttpStatus::HTTP_OK
        );
    }

    /**
     * When using OTP login, consume a step-up OTP issued via `POST .../two-factor/reauthentication/otp`.
     *
     * @return JsonResponse|null Non-null when verification failed and the response should be returned immediately.
     */
    protected function verifySensitiveStepUpIfNeeded(Request $request): ?JsonResponse
    {
        if ($this->authLogin->loginType() !== LoginType::Otp) {
            return null;
        }

        $user = $request->user();

        try {
            $this->verifySensitiveActionOtpAction->handle($request, $user);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('api.validation_error');

            return sendResponse(
                status: false,
                message: $message,
                data: ['errors' => $e->errors()],
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return null;
    }
}
