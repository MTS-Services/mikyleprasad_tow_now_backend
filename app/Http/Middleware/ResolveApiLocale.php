<?php

namespace App\Http\Middleware;

use App\Services\Localization\ApplicationLocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * API locale ordering follows {@see RequestPreferenceResolution}:
 * GET/HEAD/OPTIONS: X-App-Locale → user preferred_language → Accept-Language → fallback.
 * Other methods: user preferred_language → X-App-Locale → Accept-Language → fallback.
 */
class ResolveApiLocale
{
    public function __construct(
        private readonly ApplicationLocaleResolver $localeResolver,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('localization.supported_locales', ['en']);

        $locale = $this->localeResolver->resolveForApiRequest($request, $supported);
        App::setLocale($locale);

        return $next($request);
    }
}
