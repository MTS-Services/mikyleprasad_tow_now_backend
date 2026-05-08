<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::DRIVER) {
            return response()->json(['message' => __('auth.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
