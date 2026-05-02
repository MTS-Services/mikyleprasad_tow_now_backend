<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('preferredCurrency');

        return sendResponse(
            status: true,
            message: __('api.user_details'),
            data: new UserResource($user),
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
