<?php

namespace Leantime\Domain\Supportportal\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Supportportal\Controllers\Concerns\ProvidesPortalViewData;
use Leantime\Domain\Supportportal\Services\PortalAccess;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Symfony\Component\HttpFoundation\Response;

class Home extends Controller
{
    use ProvidesPortalViewData;

    private PortalResolver $portalResolver;

    private PortalAccess $portalAccess;

    public function init(
        PortalResolver $portalResolver,
        PortalAccess $portalAccess
    ): void {
        $this->portalResolver = $portalResolver;
        $this->portalAccess = $portalAccess;
    }

    public function get(array $params): Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());

        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        if (session()->exists('userdata.id')) {
            $this->portalAccess->ensurePortalSession($portal);

            return Frontcontroller::redirect($this->supportUrl('/support/tickets'));
        }

        $this->assignPortal($portal);

        return $this->tpl->display('global::supportportal.home', 'supportportal');
    }
}
