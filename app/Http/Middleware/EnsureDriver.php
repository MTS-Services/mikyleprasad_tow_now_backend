<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->role !== UserRole::DRIVER) {
            return sendResponse(
                status: false,
                message: __('auth.unauthorized'),
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        if ($user->approval_status === ApprovalStatus::PENDING) {
            return sendResponse(
                status: false,
                message: __('auth.driver.pending'),
                data: [
                    'approval_status' => $user->approval_status,
                ],
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        if ($user->approval_status === ApprovalStatus::REJECTED) {
            return sendResponse(
                status: false,
                message: __('auth.driver.rejected'),
                data: [
                    'approval_status' => $user->approval_status,
                ],
                statusCode: Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
