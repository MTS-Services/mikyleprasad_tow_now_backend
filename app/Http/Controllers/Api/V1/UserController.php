<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\User\UpdateFcmTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('preferredCurrency', 'vehicle')
            ->loadCount('driverReviews')
            ->loadAvg('driverReviews', 'rating');

        return sendResponse(
            status: true,
            message: __('api.user_details'),
            data: new UserResource($user),
            statusCode: HttpStatus::HTTP_OK
        );
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->validated('fcm_token');

        $user->forceFill(['fcm_token' => $token])->save();

        Cache::put('fcm_token_' . $user->getAuthIdentifier(), $token, now()->addDays(30));

        return sendResponse(
            status: true,
            message: 'FCM token updated.',
            data: null,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
