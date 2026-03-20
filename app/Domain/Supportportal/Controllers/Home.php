<?php

namespace Leantime\Domain\Supportportal\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Supportportal\Services\PortalAccess;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Symfony\Component\HttpFoundation\Response;

class Home extends Controller
{
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

            return Frontcontroller::redirect(BASE_URL.'/support/tickets');
        }

        $this->assignPortal($portal);

        return $this->tpl->display('supportportal.home', 'supportportal');
    }

    protected function assignPortal(array $portal): void
    {
        $this->tpl->assign('portal', $portal);
        $this->tpl->assign('sitename', $portal['brandName'].' Support');
        $this->tpl->assign('portalBrandName', $portal['brandName']);
        $this->tpl->assign('portalLogoUrl', $portal['brandLogo']);
        $this->tpl->assign('primaryColor', $portal['primaryColor']);
        $this->tpl->assign('secondaryColor', $portal['secondaryColor']);
    }
}
