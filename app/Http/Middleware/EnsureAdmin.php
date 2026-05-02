<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== UserRole::ADMIN) {
            return response()->json(['message' => __('auth.unauthorized')], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
