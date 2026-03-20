<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Supportportal\Middleware\SupportHostRedirect;
use Leantime\Domain\Supportportal\Services\PortalResolver;

EventDispatcher::add_filter_listener('leantime.core.middleware.authcheck.*.publicActions', function ($actions) {
    $actions[] = 'support';
    $actions[] = 'support.login';
    $actions[] = 'support.register';

    return array_values(array_unique($actions));
});

EventDispatcher::add_filter_listener('leantime.core.middleware.authcheck.*.loginRoute', function ($route, $params) {
    $request = $params['request'] ?? null;
    $path = $request?->path() ?? '';

    if ($request === null) {
        return $route;
    }

    $portal = app(PortalResolver::class)->resolveCurrentHost($request->getHost());
    if ($portal === false) {
        return $route;
    }

    $basePath = rtrim($request->getBasePath(), '/');
    $supportBase = $request->getSchemeAndHttpHost().$basePath.'/support';

    if ($path === '/' || $path === '') {
        return $supportBase;
    }

    if ($path === 'support' || str_starts_with($path, 'support/')) {
        return $supportBase.'/login';
    }

    return $route;
});

EventDispatcher::add_filter_listener('leantime.core.http.httpkernel.*.middleware', function (array $middleware) {
    array_unshift($middleware, SupportHostRedirect::class);

    return array_values(array_unique($middleware));
});
