<?php

namespace Leantime\Domain\Supportportal\Middleware;

use Closure;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SupportHostRedirect
{
    public function __construct(
        private PortalResolver $portalResolver
    ) {}

    public function handle($request, Closure $next)
    {
        $portal = $this->portalResolver->resolveCurrentHost($request->getHost());

        if (
            $portal !== false
            && in_array($request->path(), ['/', ''], true)
        ) {
            $basePath = rtrim($request->getBasePath(), '/');

            return new RedirectResponse($request->getSchemeAndHttpHost().$basePath.'/support');
        }

        return $next($request);
    }
}
