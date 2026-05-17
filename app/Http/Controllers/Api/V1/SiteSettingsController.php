<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\Auth\AuthLoginConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class SiteSettingsController extends Controller
{
    public function index(AuthLoginConfiguration $authLogin): JsonResponse
    {
        $siteSettings = Cache::remember(SiteSetting::PUBLIC_CACHE_KEY, now()->addMinutes(5), function () use ($authLogin) {
            $row = SiteSetting::query()->first();

            return array_merge($row ? $row->toArray() : [], [
                'login_type' => $authLogin->loginType()->value,
                'otp_code_length' => $authLogin->otpCodeLength(),
            ]);
        });

        return sendResponse(
            status: true,
            message: 'Site settings fetched successfully.',
            data: $siteSettings,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
