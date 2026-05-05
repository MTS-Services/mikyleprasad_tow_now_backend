<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Auth\EnsureLoginIdentifierIsVerifiedAction;
use App\Actions\Api\V1\Otp\RequestLoginOtpAction;
use App\Actions\Api\V1\Otp\VerifyLoginOtpAction;
use App\Enums\ApiErrorCode;
use App\Enums\LoginType;
use App\Http\Controllers\Concerns\CompletesApiLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Otp\LoginOtpVerifyRequest;
use App\Http\Requests\Api\V1\Otp\SendLoginOtpRequest;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Http\JsonResponse;
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
}
