<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserProfileResource;
use App\Services\CustomerServce;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ProfileController extends Controller
{
        public function __construct(
        private readonly CustomerServce $customerService
    ) {}

        public function profile(Request $request)
    {

        $customer = $this->customerService->getCustomerProfile();

        if (! $customer) {
            return sendResponse(false, 'Customer profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Customer profile fetched successfully.', new UserProfileResource($customer), HttpStatus::HTTP_OK);
    }

    public function update(Request $request)
    {

        $customer = $this->customerService->updateCustomerProfile($request->all());

        if (! $customer) {
            return sendResponse(false, 'Customer profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Customer profile updated successfully.', new UserProfileResource($customer), HttpStatus::HTTP_OK);
    }
}
