<?php

use Illuminate\Support\Facades\Route;
use Leantime\Domain\Supportportal\Controllers\Access;
use Leantime\Domain\Supportportal\Controllers\Home;
use Leantime\Domain\Supportportal\Controllers\Tickets;

if (! function_exists('supportPortalEnvironmentValue')) {
    function supportPortalEnvironmentValue(string $key): mixed
    {
        $value = getenv($key);

        if ($value !== false && $value !== null && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return false;
    }
}

if (! function_exists('supportPortalConfiguredHosts')) {
    function supportPortalConfiguredHosts(): array
    {
        $hosts = [];
        $map = supportPortalEnvironmentValue('LEAN_SUPPORT_PORTALS');

        if (is_string($map) && $map !== '') {
            $decoded = json_decode($map, true);
            if (is_array($decoded)) {
                $hosts = array_merge($hosts, array_keys($decoded));
            }
        }

        foreach (array_keys($_ENV + $_SERVER) as $key) {
            if (! str_starts_with($key, 'LEAN_SUPPORT_PORTAL_') || $key === 'LEAN_SUPPORT_PORTALS') {
                continue;
            }

            $host = strtolower(str_replace('_', '.', substr($key, strlen('LEAN_SUPPORT_PORTAL_'))));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        $hosts = array_values(array_unique(array_filter($hosts)));

        return $hosts;
    }
}

foreach (supportPortalConfiguredHosts() as $supportHost) {
    Route::domain($supportHost)->group(function () {
        Route::match(['get'], '/', [Home::class, 'get']);
        Route::match(['get', 'post'], '/login', [Access::class, 'login']);
        Route::match(['get', 'post'], '/register', [Access::class, 'register']);
        Route::post('/logout', [Access::class, 'logout']);

        Route::match(['get'], '/tickets', [Tickets::class, 'index']);
        Route::match(['get', 'post'], '/tickets/new', [Tickets::class, 'new']);
        Route::match(['get', 'post'], '/tickets/{id}', [Tickets::class, 'show'])->whereNumber('id');
    });
}

Route::domain('support.{domain}')
    ->where(['domain' => '.*'])
    ->group(function () {
        Route::match(['get'], '/', [Home::class, 'get']);
        Route::match(['get', 'post'], '/login', [Access::class, 'login']);
        Route::match(['get', 'post'], '/register', [Access::class, 'register']);
        Route::post('/logout', [Access::class, 'logout']);

        Route::match(['get'], '/tickets', [Tickets::class, 'index']);
        Route::match(['get', 'post'], '/tickets/new', [Tickets::class, 'new']);
        Route::match(['get', 'post'], '/tickets/{id}', [Tickets::class, 'show'])->whereNumber('id');
    });

Route::match(['get'], '/support', [Home::class, 'get']);
Route::match(['get', 'post'], '/support/login', [Access::class, 'login']);
Route::match(['get', 'post'], '/support/register', [Access::class, 'register']);
Route::post('/support/logout', [Access::class, 'logout']);

Route::match(['get'], '/support/tickets', [Tickets::class, 'index']);
Route::match(['get', 'post'], '/support/tickets/new', [Tickets::class, 'new']);
Route::match(['get', 'post'], '/support/tickets/{id}', [Tickets::class, 'show'])->whereNumber('id');
