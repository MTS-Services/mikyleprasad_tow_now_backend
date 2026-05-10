<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserProfileResource;
use App\Services\UserServce;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserServce $userServce
    ) {}



    // ProfileController.php

    public function update(Request $request)
    {
        $data = $this->userServce->updateProfile($request, $request->all());

        if (! $data) {
            return sendResponse(false, 'User profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }
        return sendResponse(true, 'Data updated successfully.', new UserProfileResource($data['user']), HttpStatus::HTTP_OK);
    }
}