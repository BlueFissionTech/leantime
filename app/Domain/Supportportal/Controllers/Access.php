<?php

namespace Leantime\Domain\Supportportal\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Supportportal\Controllers\Concerns\ProvidesPortalViewData;
use Leantime\Domain\Supportportal\Services\PortalAccess;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Symfony\Component\HttpFoundation\Response;

class Access extends Controller
{
    use ProvidesPortalViewData;

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

        return $this->tpl->display('global::supportportal.login', 'supportportal');
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

        return $this->tpl->display('global::supportportal.register', 'supportportal');
    }

    public function logout(array $params): Response
    {
        $this->authService->logout();

        return Frontcontroller::redirect($this->supportUrl('/'));
    }

    private function postLogin(array $params): Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $payload = $this->incomingRequest->all();

        $result = $this->portalAccess->loginAndScope(
            $portal,
            $payload['email'] ?? '',
            $payload['password'] ?? ''
        );

        if (! $result['ok']) {
            $this->tpl->setNotification($result['message'], 'error');

            return Frontcontroller::redirect($this->supportUrl('/login'));
        }

        return Frontcontroller::redirect($this->supportUrl('/tickets'));
    }

    private function postRegister(array $params): Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $payload = $this->incomingRequest->all();

        if (! $portal['allowSelfSignup']) {
            $this->tpl->setNotification('This support portal does not allow self-signup.', 'error');

            return Frontcontroller::redirect($this->supportUrl('/login'));
        }

        $result = $this->portalAccess->registerAndLogin($portal, $payload);

        if (! $result['ok']) {
            $this->tpl->setNotification($result['message'], 'error');

            return Frontcontroller::redirect($this->supportUrl('/register'));
        }

        return Frontcontroller::redirect($this->supportUrl('/tickets'));
    }
}
