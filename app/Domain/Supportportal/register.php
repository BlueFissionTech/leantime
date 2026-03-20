<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Supportportal\Middleware\SupportHostRedirect;

EventDispatcher::add_filter_listener('leantime.core.middleware.authcheck.publicActions', function ($actions) {
    $actions[] = 'support';
    $actions[] = 'support.login';
    $actions[] = 'support.register';

    return array_values(array_unique($actions));
});

EventDispatcher::add_filter_listener('leantime.core.middleware.authcheck.loginRoute', function ($route, $params) {
    $request = $params['request'] ?? null;
    $path = $request?->path() ?? '';

    if ($path === 'support' || str_starts_with($path, 'support/')) {
        return 'support.login';
    }

    return $route;
});

EventDispatcher::add_filter_listener('leantime.core.http.httpkernel.*.middleware', function (array $middleware) {
    array_unshift($middleware, SupportHostRedirect::class);

    return array_values(array_unique($middleware));
});
