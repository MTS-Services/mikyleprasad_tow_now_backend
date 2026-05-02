<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LoginHistoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class LoginHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 30;

        $paginator = $request->user()
            ->loginHistories()
            ->paginate($perPage)
            ->withQueryString();

        return sendResponse(
            status: true,
            message: __('api.login_history_fetched_successfully'),
            data: LoginHistoryResource::collection($paginator),
            statusCode: HttpStatus::HTTP_OK
        );
    }
}
