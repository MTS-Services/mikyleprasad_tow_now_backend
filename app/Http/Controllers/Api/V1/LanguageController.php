<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class LanguageController extends Controller
{
    /**
     * List all active languages available for translation.
     * Result is served from cache — zero DB queries after first request.
     *
     * GET /api/v1/languages
     */
    public function index(): JsonResponse
    {
        $languages = Cache::remember('languages:active:v1', now()->addMinutes(10), function () {
            return Language::allActive()
                ->map(fn (Language $lang) => [
                    'locale' => $lang->locale,
                    'name' => $lang->name,
                    'native_name' => $lang->native_name,
                    'country_code' => $lang->country_code,
                    'is_rtl' => $lang->is_rtl,
                    'is_default' => $lang->is_default,
                ]);
        });

        return sendResponse(
            status: true,
            message: __('api.languages_fetched_successfully'),
            data: $languages,
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
