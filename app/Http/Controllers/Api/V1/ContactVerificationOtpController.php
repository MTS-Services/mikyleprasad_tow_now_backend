<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\V1\Otp\RequestContactVerificationOtpAction;
use App\Actions\Api\V1\Otp\VerifyContactVerificationOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Otp\ContactVerificationOtpRequestRequest;
use App\Http\Requests\Api\V1\Otp\ContactVerificationOtpVerifyRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ContactVerificationOtpController extends Controller
{
    public function request(ContactVerificationOtpRequestRequest $request, RequestContactVerificationOtpAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return sendResponse(
                status: false,
                message: __('api.unauthenticated'),
                data: null,
                statusCode: HttpStatus::HTTP_UNAUTHORIZED
            );
        }

        return $action->handle($request, $user);
    }

    public function verify(ContactVerificationOtpVerifyRequest $request, VerifyContactVerificationOtpAction $action): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return sendResponse(
                status: false,
                message: __('api.unauthenticated'),
                data: null,
                statusCode: HttpStatus::HTTP_UNAUTHORIZED
            );
        }

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
}
