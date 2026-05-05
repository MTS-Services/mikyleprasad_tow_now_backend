<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Auth\CompletePasswordResetWithOtpAction;
use App\Actions\Api\V1\Auth\EnsureLoginIdentifierIsVerifiedAction;
use App\Actions\Api\V1\Auth\RegisterUserAction;
use App\Actions\Api\V1\Auth\RequestPasswordResetOtpAction;
use App\Actions\Api\V1\Auth\RevokePassportTokensAction;
use App\Actions\Api\V1\Otp\RequestLoginOtpAction;
use App\Actions\Api\V1\Otp\VerifyContactVerificationOtpAction;
use App\Enums\ApiErrorCode;
use App\Enums\LoginIdentifierType;
use App\Enums\LoginType;
use App\Http\Controllers\Concerns\CompletesApiLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisteredContactVerificationOtpVerifyRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\TwoFactor\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthController extends Controller
{
    use CompletesApiLogin;

    public function __construct(
        protected AuthLoginConfiguration $authLogin
    ) {}

    public function register(Request $request, RegisterUserAction $registerUserAction): JsonResponse
    {

        $result = $registerUserAction->handle($request);

        return sendResponse(
            status: true,
            message: __('api.registration_verification_sent'),
            data: [
                'user' => new UserResource($result['user']),
                'verification_channel' => $result['verification_channel'],
                'expires_in_minutes' => $result['expires_in_minutes'],
            ],
            statusCode: HttpStatus::HTTP_CREATED
        );
    }

    public function verifyRegisteredContact(
        RegisteredContactVerificationOtpVerifyRequest $request,
        VerifyContactVerificationOtpAction $action
    ): JsonResponse {
        $identifierType = LoginIdentifierType::from($request->string('identifier_type')->toString());
        [, $identifier] = app(LoginIdentifierDetector::class)->resolve(
            $identifierType->value,
            $request->string('identifier')->toString(),
            [$identifierType]
        );

        $user = match ($identifierType) {
            LoginIdentifierType::Email => User::query()->where('email', $identifier)->first(),
            LoginIdentifierType::Phone => User::query()->where('phone', $identifier)->first(),
            LoginIdentifierType::Username => User::query()->where('username', $identifier)->first(),
        };

        if ($user === null) {
            throw ValidationException::withMessages([
                'identifier' => [__('api.no_account_exists_for_this_sign_in')],
            ]);
        }

        $request->merge([
            'channel' => $identifierType === LoginIdentifierType::Phone ? 'phone' : 'email',
        ]);

        $fresh = $action->handle($request, $user);

        return sendResponse(
            status: true,
            message: __('api.contact_verification_successful'),
            data: [
                'user' => new UserResource($fresh),
            ],
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function login(
        LoginRequest $request,
        RequestLoginOtpAction $requestLoginOtpAction,
        EnsureLoginIdentifierIsVerifiedAction $ensureLoginIdentifierIsVerifiedAction
    ): JsonResponse {

        if ($this->authLogin->loginType() === LoginType::Otp) {
            $otpRequest = $this->requestForLoginOtp($request);

            return $requestLoginOtpAction->handle($otpRequest);
        }

        $usernameField = config('fortify.username', 'email');
        $username = $request->string($usernameField)->toString();

        $user = User::query()->where($usernameField, $username)->first();

        if (! $user || $user->password === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return sendResponse(status: false, message: __('auth.failed'), statusCode: HttpStatus::HTTP_UNAUTHORIZED);
        }

        $ensureLoginIdentifierIsVerifiedAction->handle($request, $user);

        return $this->respondAfterPrimaryAuthentication($request, $user);
    }

    /**
     * Map Fortify login field to the shape expected by {@see RequestLoginOtpAction}.
     */
    private function requestForLoginOtp(LoginRequest $request): Request
    {
        $allowed = $this->authLogin->loginIdentifierTypes();
        $identifierValue = LoginIdentifierDetector::rawCredentialStringFromRequest($request, true);
        $explicitType = $request->string('identifier_type')->toString() ?: null;

        [$type, $normalized] = app(LoginIdentifierDetector::class)->resolve(
            $explicitType,
            $identifierValue,
            $allowed
        );

        $request->merge([
            'identifier' => $normalized,
            'identifier_type' => $type->value,
        ]);

        return $request;
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $token = $request->string('two_factor_token')->toString();
        $cacheKey = 'api_2fa_login:'.$token;
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            return sendResponse(
                status: false,
                message: __('api.two_factor_invalid'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = User::query()->find($payload['user_id']);

        if (! $user || ! $user->hasEnabledTwoFactorAuthentication()) {
            Cache::forget($cacheKey);

            return sendResponse(
                status: false,
                message: __('api.two_factor_invalid'),
                data: null,
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $provider = app(TwoFactorAuthenticationProvider::class);

        if ($request->filled('recovery_code')) {
            $recovery = $request->string('recovery_code')->toString();
            $codes = $user->recoveryCodes();
            $match = collect($codes)->first(fn (string $c): bool => hash_equals($c, $recovery));

            if ($match === null) {
                return sendResponse(
                    status: false,
                    message: __('api.two_factor_invalid_code'),
                    data: null,
                    statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $user->replaceRecoveryCode($match);
        } else {
            $code = $request->string('code')->toString();
            $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

            if (! $provider->verify($secret, $code)) {
                return sendResponse(
                    status: false,
                    message: __('api.two_factor_invalid_code'),
                    data: null,
                    statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        Cache::forget($cacheKey);

        $deviceName = $request->has('device_name')
            ? $request->string('device_name')->toString()
            : (string) ($payload['device_name'] ?? '');

        return $this->issueLoginTokenResponse($request, $user, $deviceName);
    }

    public function logout(Request $request, RevokePassportTokensAction $revokePassportTokensAction): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return sendResponse(
                status: false,
                message: __('api.unauthenticated'),
                data: null,
                statusCode: HttpStatus::HTTP_UNAUTHORIZED
            );
        }

        $revokePassportTokensAction->handle($user);

        return sendResponse(status: true, message: __('api.logout_successful'), data: null, statusCode: HttpStatus::HTTP_OK);
    }

    public function forgotPassword(ForgotPasswordRequest $request, RequestPasswordResetOtpAction $requestPasswordResetOtpAction): JsonResponse
    {
        if ($this->authLogin->loginType() === LoginType::Otp) {
            return sendResponse(
                status: false,
                message: __('api.password_reset_not_available'),
                data: null,
                statusCode: HttpStatus::HTTP_FORBIDDEN,
                additional: ['code' => ApiErrorCode::PasswordResetNotAvailable->value]
            );
        }

        return $requestPasswordResetOtpAction->handle($request);
    }

    public function resetPassword(ResetPasswordRequest $request, CompletePasswordResetWithOtpAction $completePasswordResetWithOtpAction): JsonResponse
    {
        if ($this->authLogin->loginType() === LoginType::Otp) {
            return sendResponse(
                status: false,
                message: __('api.password_reset_not_available'),
                data: null,
                statusCode: HttpStatus::HTTP_FORBIDDEN,
                additional: ['code' => ApiErrorCode::PasswordResetNotAvailable->value]
            );
        }

        try {
            return $completePasswordResetWithOtpAction->handle($request);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('api.validation_error');

            return sendResponse(
                status: false,
                message: $message,
                data: ['errors' => $e->errors()],
                statusCode: HttpStatus::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
