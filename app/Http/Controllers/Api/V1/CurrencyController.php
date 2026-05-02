<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCurrencyResource;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class CurrencyController extends Controller
{
    public function index(): JsonResponse
    {
        $currencies = Currency::query()
            ->active()
            ->enabledInConfig()
            ->ordered()
            ->get();

        return sendResponse(
            status: true,
            message: __('common.success'),
            data: PublicCurrencyResource::collection($currencies),
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
