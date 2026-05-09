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

        $admin = $this->adminService->getAdminProfile($request->user()->id);

        if (! $admin) {
            return sendResponse(false, 'Admin profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Admin profile fetched successfully.', new AdminProfileResource($admin), HttpStatus::HTTP_OK);
    }

    public function update(Request $request)
    {
        $admin = $this->adminService->updateAdminProfile($request, $request->all());  

        if (! $admin) {
            return sendResponse(false, 'Admin profile not found.', null, HttpStatus::HTTP_NOT_FOUND);
        }

        return sendResponse(true, 'Admin profile updated successfully.', new AdminProfileResource($admin), HttpStatus::HTTP_OK);
    }
}
