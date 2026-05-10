<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminProfileResource;
use App\Services\AdminServce;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class ProfileController extends Controller
{
    public function __construct(
        private AdminServce $adminService
    ) {}

public function index(Request $request)
{
    $data = $this->adminService->getAdminProfile($request);

    if (! $data) {
        return sendResponse(false, 'Admin profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
    }

    return sendResponse(true, 'Data fetched successfully.', new AdminProfileResource($data), HttpStatus::HTTP_OK);
}

public function update(Request $request)
{
    $data = $this->adminService->updateAdminProfile($request, $request->all());

    if (! $data) {
        return sendResponse(false, 'Admin profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
    }

    return sendResponse(true, 'Data updated successfully.', new AdminProfileResource($data), HttpStatus::HTTP_OK);
}
}