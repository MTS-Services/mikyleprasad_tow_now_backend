<?php

namespace App\Http\Middleware;

use App\Services\Localization\ApplicationLocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web locale uses the same ordering rules as the API ({@see ApplicationLocaleResolver::resolveForWebRequest}).
 */
class SetLocale
{
    public function __construct(
        private readonly ApplicationLocaleResolver $localeResolver,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isApiRequest($request)) {
            return $next($request);
        }

        $supported = config('localization.supported_locales', ['en']);
        App::setLocale($this->localeResolver->resolveForWebRequest($request, $supported));

        return $next($request);
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*');
    }
}
