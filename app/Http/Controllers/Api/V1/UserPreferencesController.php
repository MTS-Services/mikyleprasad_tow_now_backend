<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateUserPreferencesRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserPreferencesController extends Controller
{
    public function update(UpdateUserPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($currency = $request->currency()) {
            $user->preferred_currency_id = $currency->id;
        }

        if (($loc = $request->normalizedLocale()) !== null) {
            $user->locale = $loc;
        }

        if (($tz = $request->normalizedTimezone()) !== null) {
            $user->timezone = $tz;
        }

        $user->save();

        if (isset($tz) && is_string($tz) && $tz !== '') {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }

        $user->loadMissing(['preferredCurrency']);

        return sendResponse(
            status: true,
            message: __('common.updated'),
            data: new UserResource($user),
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
