<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SiteSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $siteSettings = Cache::remember('site-settings:public:v1', now()->addMinutes(5), function () {
            return SiteSetting::first();
        });

        return sendResponse(
            status: true,
            message: 'Site settings fetched successfully.',
            data: $siteSettings,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
