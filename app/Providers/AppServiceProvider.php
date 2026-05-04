<?php

namespace App\Providers;

use App\Contracts\Currency\CurrencyContextInterface;
use App\Contracts\Sms\SmsGateway;
use App\Enums\LoginType;
use App\Services\Auth\AuthLoginConfiguration;
use App\Services\Auth\LoginIdentifierDetector;
use App\Services\Currency\CurrencyContext;
use App\Services\Currency\CurrencyDisplayResolver;
use App\Services\Sms\UnavailableSmsGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrencyContextInterface::class, function ($app) {
            return new CurrencyContext($app->make(CurrencyDisplayResolver::class));
        });

        $this->app->singleton(SmsGateway::class, UnavailableSmsGateway::class);
    }

    public function boot(): void
    {
        $this->validateCurrencyConfig();
        RateLimiter::for('api-login', function (Request $request) {
            $authLogin = app(AuthLoginConfiguration::class);

            if ($authLogin->loginType() === LoginType::Otp && $request->is('api/v1/login')) {
                $allowed = $authLogin->loginIdentifierTypes();
                $rawIdentifier = LoginIdentifierDetector::rawCredentialStringFromRequest($request, true);
                $segment = LoginIdentifierDetector::throttleSegment(
                    $rawIdentifier,
                    $request->input('identifier_type'),
                    $allowed
                );

                $throttleKey = Str::transliterate($segment.'|'.$request->ip());

                return Limit::perMinute(5)->by($throttleKey);
            }

            $usernameField = config('fortify.username', 'email');

            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input($usernameField)).'|'.$request->ip()
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('api-register', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );

            return Limit::perMinute(3)->by($throttleKey);
        });

        RateLimiter::for('api-password-reset', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );

            return Limit::perMinute(3)->by($throttleKey);
        });

        RateLimiter::for('api-password-reset-verify', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('api-otp-request', function (Request $request) {
            $authLogin = app(AuthLoginConfiguration::class);
            $raw = LoginIdentifierDetector::rawCredentialStringFromRequest($request, false);
            $segment = LoginIdentifierDetector::throttleSegment(
                $raw,
                $request->input('identifier_type'),
                $authLogin->loginIdentifierTypes()
            );
            $throttleKey = Str::transliterate($segment.'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('api-otp-verify', function (Request $request) {
            $authLogin = app(AuthLoginConfiguration::class);
            $raw = LoginIdentifierDetector::rawCredentialStringFromRequest($request, false);
            $segment = LoginIdentifierDetector::throttleSegment(
                $raw,
                $request->input('identifier_type'),
                $authLogin->loginIdentifierTypes()
            );
            $throttleKey = Str::transliterate($segment.'|'.$request->ip());

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('api-verification-otp-request', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by((string) $userId.'|'.$request->ip());
        });

        RateLimiter::for('api-verification-otp-verify', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(10)->by((string) $userId.'|'.$request->ip());
        });

        RateLimiter::for('api-sensitive-action-otp-request', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(5)->by((string) $userId.'|'.$request->ip());
        });
    }

    private function validateCurrencyConfig(): void
    {
        $base = strtoupper((string) config('currency.base_currency', 'USD'));
        $enabled = array_map('strtoupper', config('currency.enabled_codes', []));

        if ($enabled !== [] && ! in_array($base, $enabled, true)) {
            throw new \LogicException('config currency.base_currency must be listed in currency.enabled_codes.');
        }
    }
}
