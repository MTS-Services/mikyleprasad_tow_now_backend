<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Auth\EnsureLoginIdentifierIsVerifiedAction;
use App\Actions\Api\V1\Auth\RegisterUserAction;
use App\Actions\Api\V1\Otp\RequestLoginOtpAction;
use App\Actions\Api\V1\Otp\VerifyLoginOtpAction;
use App\Enums\ApiErrorCode;
use App\Enums\LoginType;
use App\Http\Controllers\Concerns\CompletesApiLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Otp\LoginOtpVerifyRequest;
use App\Http\Requests\Api\V1\Otp\SendLoginOtpRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class OtpAuthController extends Controller
{
    use CompletesApiLogin;

    public function __construct(
        protected AuthLoginConfiguration $authLogin
    ) {}

    public function request(SendLoginOtpRequest $request, RequestLoginOtpAction $action): JsonResponse
    {
        if ($this->authLogin->loginType() !== LoginType::Otp) {
            return sendResponse(
                status: false,
                message: __('api.login_otp_disabled'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                additional: ['code' => ApiErrorCode::LoginOtpDisabled->value]
            );
        }

        return $action->handle($request);
    }

    public function verify(
        LoginOtpVerifyRequest $request,
        VerifyLoginOtpAction $action,
        EnsureLoginIdentifierIsVerifiedAction $ensureLoginIdentifierIsVerifiedAction
    ): JsonResponse {
        if ($this->authLogin->loginType() !== LoginType::Otp) {
            return sendResponse(
                status: false,
                message: __('api.login_otp_disabled'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY,
                additional: ['code' => ApiErrorCode::LoginOtpDisabled->value]
            );
        }

        $user = $action->handle($request);
        $ensureLoginIdentifierIsVerifiedAction->handle($request, $user);

        return $this->respondAfterPrimaryAuthentication($request, $user);
    }

    public function resendRegistration(Request $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->resend($request);

        return sendResponse(
            status: true,
            message: __('api.otp_sent_to_email'),
            data: [
                'verification_channel' => $result['verification_channel'],
                'expires_in_minutes' => $result['expires_in_minutes'],
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function verifyRegistration(Request $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->verify($request);

        return sendResponse(
            status: true,
            message: __('api.registration_successful'),
            data: [
                'token_type' => $result['token_type'],
                'access_token' => $result['access_token'],
                'user' => new UserResource($result['user']),
            ],
            statusCode: HttpStatus::HTTP_CREATED
        );
    }
}
