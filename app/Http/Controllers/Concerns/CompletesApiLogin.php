<?php

namespace App\Http\Controllers\Concerns;

use App\Actions\Api\V1\Auth\IssuePersonalAccessTokenAction;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Auth\UserLoginHistoryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

trait CompletesApiLogin
{
    protected function respondAfterPrimaryAuthentication(Request $request, User $user): JsonResponse
    {
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
}
