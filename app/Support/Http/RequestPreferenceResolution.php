<?php

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Chooses whether client headers or persisted user preferences take priority for
 * locale and display currency on the current request.
 *
 * Safe (read) methods: header first — matches typical “what I’m asking to see” (GET, listings, messages on read).
 * Mutating methods: user preference first — matches transactional state (checkout, purchases, PATCH that writes DB).
 */
final class RequestPreferenceResolution
{
    /**
     * When true, resolve header override (X-App-Locale / X-App-Currency) before the authenticated user's stored preferences.
     */
    public static function headerBeforeUserPreferences(Request $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true);
    }
}
