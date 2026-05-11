<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureDriver;
use App\Http\Middleware\PublicApiCacheHeaders;
use App\Http\Middleware\EnsureUser;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
        $middleware->prepend(SetLocale::class);

        $middleware->alias([
            'role.user' => EnsureUser::class,
            'role.driver' => EnsureDriver::class,
            'role.admin' => EnsureAdmin::class,
            'public.cache' => PublicApiCacheHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
