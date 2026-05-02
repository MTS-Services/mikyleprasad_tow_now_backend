<?php

namespace App\Services\Localization;

use App\Support\Http\RequestPreferenceResolution;
use Illuminate\Http\Request;

class ApplicationLocaleResolver
{
    /**
     * Resolve locale for API: order depends on HTTP method (see {@see RequestPreferenceResolution}).
     *
     * @param  array<int, string>  $supported
     */
    public function resolveForApiRequest(Request $request, array $supported): string
    {
        return $this->resolveForRequest(
            $request,
            $supported,
            RequestPreferenceResolution::headerBeforeUserPreferences($request)
        );
    }

    /**
     * Resolve locale for web (non-api) routes; same ordering rules as the API.
     *
     * @param  array<int, string>  $supported
     */
    public function resolveForWebRequest(Request $request, array $supported): string
    {
        return $this->resolveForRequest(
            $request,
            $supported,
            RequestPreferenceResolution::headerBeforeUserPreferences($request)
        );
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function resolveForRequest(Request $request, array $supported, bool $headerBeforeUser): string
    {
        if ($supported === []) {
            $supported = ['en'];
        }

        if ($headerBeforeUser) {
            $fromHeader = $this->localeFromOverrideHeader($request, $supported);
            if ($fromHeader !== null) {
                return $fromHeader;
            }

            // Treat Accept-Language as a "header preference" for safe/read requests.
            $fromAccept = $request->getPreferredLanguage($supported);
            if (is_string($fromAccept) && in_array($fromAccept, $supported, true)) {
                return $fromAccept;
            }

            $fromUser = $this->localeFromUserPreference($request, $supported);
            if ($fromUser !== null) {
                return $fromUser;
            }
        } else {
            $fromUser = $this->localeFromUserPreference($request, $supported);
            if ($fromUser !== null) {
                return $fromUser;
            }

            $fromHeader = $this->localeFromOverrideHeader($request, $supported);
            if ($fromHeader !== null) {
                return $fromHeader;
            }
        }

        $fromAccept = $request->getPreferredLanguage($supported);
        if (is_string($fromAccept) && in_array($fromAccept, $supported, true)) {
            return $fromAccept;
        }

        $fallback = (string) config('app.fallback_locale', 'en');

        return in_array($fallback, $supported, true) ? $fallback : $supported[0];
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function localeFromUserPreference(Request $request, array $supported): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $pref = (string) ($user->locale ?? '');

        return $this->normalizeToSupportedLocale($pref, $supported);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function localeFromOverrideHeader(Request $request, array $supported): ?string
    {
        if (! config('localization.locale_override_enabled', true)) {
            return null;
        }

        $headerName = (string) config('localization.locale_override_header', 'X-App-Locale');
        $candidate = $request->header($headerName);

        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return $this->normalizeToSupportedLocale($candidate, $supported);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function normalizeToSupportedLocale(string $candidate, array $supported): ?string
    {
        $normalized = strtolower(str_replace('_', '-', trim($candidate)));

        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, $supported, true)) {
            return $normalized;
        }

        $primary = strtolower(explode('-', $normalized)[0]);

        return in_array($primary, $supported, true) ? $primary : null;
    }
}
