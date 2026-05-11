<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicApiCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'public, max-age=30, s-maxage=60, stale-while-revalidate=120');
            $response->headers->set('Vary', 'Accept-Encoding, Accept-Language, X-Low-Bandwidth');
        }

        return $response;
    }
}
