<?php

use App\Http\Middleware\ResolveApiLocale;
use App\Http\Middleware\ResolveApiTimezone;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveApiLocale::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/public.php'));

Route::middleware(['auth:api', ResolveApiLocale::class, ResolveApiTimezone::class])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1/common.php'));

Route::middleware(['auth:api', ResolveApiLocale::class, ResolveApiTimezone::class, 'role.user'])
    ->prefix('v1/user')
    ->name('api.v1.user.')
    ->group(base_path('routes/api/v1/user.php'));

Route::middleware(['auth:api', ResolveApiLocale::class, ResolveApiTimezone::class, 'role.driver'])
    ->prefix('v1/driver')
    ->name('api.v1.driver.')
    ->group(base_path('routes/api/v1/driver.php'));
Route::middleware(['auth:api', ResolveApiLocale::class, ResolveApiTimezone::class, 'role.admin'])
    ->prefix('v1/admin')
    ->name('api.v1.admin.')
    ->group(base_path('routes/api/v1/admin.php'));
