<?php

namespace Leantime\Domain\Supportportal\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Supportportal\Services\PortalAccess;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Symfony\Component\HttpFoundation\Response;

class Access extends Controller
{
    private PortalResolver $portalResolver;

    private PortalAccess $portalAccess;

    private AuthService $authService;

    public function init(
        PortalResolver $portalResolver,
        PortalAccess $portalAccess,
        AuthService $authService
    ): void {
        $this->portalResolver = $portalResolver;
        $this->portalAccess = $portalAccess;
        $this->authService = $authService;
    }

    public function login(array $params): Response
    {
        if ($this->incomingRequest->isMethod('POST')) {
            return $this->postLogin($params);
        }

        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $this->assignPortal($portal);

        return $this->tpl->display('supportportal.login', 'supportportal');
    }

    public function register(array $params): Response
    {
        if ($this->incomingRequest->isMethod('POST')) {
            return $this->postRegister($params);
        }

        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $this->assignPortal($portal);

        return $this->tpl->display('supportportal.register', 'supportportal');
    }

    public function logout(array $params): Response
    {
        $this->authService->logout();

        return Frontcontroller::redirect(BASE_URL.'/support');
    }

    private function postLogin(array $params): Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $result = $this->portalAccess->loginAndScope(
            $portal,
            $params['email'] ?? '',
            $params['password'] ?? ''
        );

        if (! $result['ok']) {
            $this->tpl->setNotification($result['message'], 'error');

            return Frontcontroller::redirect(BASE_URL.'/support/login');
        }

        return Frontcontroller::redirect(BASE_URL.'/support/tickets');
    }

    private function postRegister(array $params): Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        if (! $portal['allowSelfSignup']) {
            $this->tpl->setNotification('This support portal does not allow self-signup.', 'error');

            return Frontcontroller::redirect(BASE_URL.'/support/login');
        }

        $result = $this->portalAccess->registerAndLogin($portal, $params);

        if (! $result['ok']) {
            $this->tpl->setNotification($result['message'], 'error');

            return Frontcontroller::redirect(BASE_URL.'/support/register');
        }

        return Frontcontroller::redirect(BASE_URL.'/support/tickets');
    }

    private function assignPortal(array $portal): void
    {
        $this->tpl->assign('portal', $portal);
        $this->tpl->assign('sitename', $portal['brandName'].' Support');
        $this->tpl->assign('portalBrandName', $portal['brandName']);
        $this->tpl->assign('portalLogoUrl', $portal['brandLogo']);
        $this->tpl->assign('primaryColor', $portal['primaryColor']);
        $this->tpl->assign('secondaryColor', $portal['secondaryColor']);
    }
}
