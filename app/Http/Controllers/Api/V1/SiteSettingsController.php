<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;

class SiteSettingsController extends Controller
{
    public function index()
    {
        $siteSettings = SiteSetting::first();
    
        return sendResponse(
            status: true,
            message: 'Site settings fetched successfully.',
            data: $siteSettings
        );
    }
}
