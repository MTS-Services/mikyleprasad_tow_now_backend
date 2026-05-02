<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Auth\IssuePersonalAccessTokenAction;
use App\Actions\Api\V1\Auth\RegisterUserAction;
use App\Actions\Api\V1\Auth\RevokePassportTokensAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\TwoFactor\TwoFactorChallengeRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\UserLoginHistoryRecorder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class AuthController extends Controller
{
    public function register(Request $request, RegisterUserAction $registerUserAction): JsonResponse
    {
        $result = $registerUserAction->handle($request);

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

    public function login(LoginRequest $request): JsonResponse
    {
        $usernameField = config('fortify.username', 'email');
        $username = $request->string($usernameField)->toString();

        $user = User::query()->where($usernameField, $username)->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return sendResponse(status: false, message: __('auth.failed'), statusCode: HttpStatus::HTTP_UNAUTHORIZED);
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $plainToken = (string) Str::uuid();
            Cache::put('api_2fa_login:'.$plainToken, [
                'user_id' => $user->id,
                'device_name' => $request->string('device_name')->toString(),
            ], now()->addMinutes(10));

            return sendResponse(
                status: true,
                message: __('api.two_factor_required'),
                data: [
                    'two_factor' => true,
                    'two_factor_token' => $plainToken,
                ],
                statusCode: HttpStatus::HTTP_OK
            );
        }

        return $this->issueLoginTokenResponse($request, $user);
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

    protected function issueLoginTokenResponse(Request $request, User $user, ?string $deviceName = null): JsonResponse
    {
        $resolvedDevice = $deviceName ?? $request->string('device_name')->toString();

        $accessToken = app(IssuePersonalAccessTokenAction::class)->handle(
            user: $user,
            deviceName: $resolvedDevice !== '' ? $resolvedDevice : null
        );

        app(UserLoginHistoryRecorder::class)->record($user, $request);

        return sendResponse(status: true, message: __('api.login_successful'), data: [
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'user' => new UserResource($user),
        ], statusCode: HttpStatus::HTTP_OK);
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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->sendResetLink($request->only('email'));

        $sent = $status === Password::RESET_LINK_SENT;
        $throttled = $status === Password::RESET_THROTTLED;

        return sendResponse(
            status: $sent,
            message: __($sent ? 'passwords.sent' : ($throttled ? 'passwords.throttled' : 'passwords.user')),
            data: null,
            statusCode: $sent ? HttpStatus::HTTP_OK : ($throttled ? HttpStatus::HTTP_TOO_MANY_REQUESTS : HttpStatus::HTTP_OK)
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $ok = $status === Password::PASSWORD_RESET;

        return sendResponse(
            status: $ok,
            message: __($ok ? 'passwords.reset' : 'passwords.token'),
            data: null,
            statusCode: $ok ? HttpStatus::HTTP_OK : HttpStatus::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
