<?php

namespace Leantime\Domain\Supportportal\Controllers\Concerns;

trait ProvidesPortalViewData
{
    protected function assignPortal(array $portal): void
    {
        $supportBaseUrl = $this->getSupportBaseUrl();
        $supportHomeUrl = $supportBaseUrl.'/support';

        $this->tpl->assign('portal', $portal);
        $this->tpl->assign('sitename', $portal['brandName'].' Support');
        $this->tpl->assign('portalBrandName', $portal['brandName']);
        $this->tpl->assign('portalLogoUrl', $portal['brandLogo']);
        $this->tpl->assign('primaryColor', $portal['primaryColor']);
        $this->tpl->assign('secondaryColor', $portal['secondaryColor']);
        $this->tpl->assign('supportBaseUrl', $supportBaseUrl);
        $this->tpl->assign('supportAssetBaseUrl', $supportBaseUrl);
        $this->tpl->assign('supportHomeUrl', $supportHomeUrl);
        $this->tpl->assign('supportLoginUrl', $supportHomeUrl.'/login');
        $this->tpl->assign('supportRegisterUrl', $supportHomeUrl.'/register');
        $this->tpl->assign('supportLogoutUrl', $supportHomeUrl.'/logout');
        $this->tpl->assign('supportTicketsUrl', $supportHomeUrl.'/tickets');
        $this->tpl->assign('supportNewTicketUrl', $supportHomeUrl.'/tickets/new');
    }

    protected function supportUrl(string $path = ''): string
    {
        $base = $this->getSupportBaseUrl();
        $normalizedPath = ltrim($path, '/');

        return $normalizedPath === '' ? $base : $base.'/'.$normalizedPath;
    }

    private function getSupportBaseUrl(): string
    {
        $basePath = rtrim($this->incomingRequest->getBasePath(), '/');

        return $this->incomingRequest->getSchemeAndHttpHost().$basePath;
    }
}
