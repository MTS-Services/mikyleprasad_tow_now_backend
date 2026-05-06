<?php

namespace App\Http\Middleware;

use App\Enums\ApprovalStatus;
use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DriverMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::DRIVER) {
            return response()->json(['message' => __('auth.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        // Check if driver is approved
        if($request->user()->approval_status !== ApprovalStatus::APPROVED) {
            return response()->json([
                'message' => __('Please wait, your account is not approved yet.')
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
