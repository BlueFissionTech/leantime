<?php

namespace Leantime\Domain\Widgets\Hxcontrollers;

use Leantime\Core\Controller\HtmxController;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;

class HighImpactNext extends HtmxController
{
    protected static string $view = 'widgets::partials.highImpactNext';

    private TicketService $ticketsService;

    public function init(TicketService $ticketsService): void
    {
        $this->ticketsService = $ticketsService;
        session(['lastPage' => BASE_URL.'/dashboard/home']);
    }

    public function get(): void
    {
        $params = $this->incomingRequest->query->all();
        $tplVars = $this->ticketsService->getHighImpactNextAssignments($params);

        array_map([$this->tpl, 'assign'], array_keys($tplVars), array_values($tplVars));
    }
}
