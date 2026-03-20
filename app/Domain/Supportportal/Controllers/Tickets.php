<?php

namespace Leantime\Domain\Supportportal\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Supportportal\Controllers\Concerns\ProvidesPortalViewData;
use Leantime\Domain\Supportportal\Repositories\SupportTickets;
use Leantime\Domain\Supportportal\Services\PortalAccess;
use Leantime\Domain\Supportportal\Services\PortalResolver;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class Tickets extends Controller
{
    use ProvidesPortalViewData;

    private PortalResolver $portalResolver;

    private PortalAccess $portalAccess;

    private SupportTickets $supportTickets;

    private TicketService $ticketService;

    private CommentService $commentService;

    public function init(
        PortalResolver $portalResolver,
        PortalAccess $portalAccess,
        SupportTickets $supportTickets,
        TicketService $ticketService,
        CommentService $commentService,
    ): void {
        $this->portalResolver = $portalResolver;
        $this->portalAccess = $portalAccess;
        $this->supportTickets = $supportTickets;
        $this->ticketService = $ticketService;
        $this->commentService = $commentService;
    }

    public function index(array $params): Response
    {
        $portal = $this->resolvePortalOrRedirect();
        if ($portal instanceof Response) {
            return $portal;
        }

        $tickets = $this->supportTickets->getPortalTickets((int) $portal['projectId'], (int) session('userdata.id'));
        $statusLabels = $this->ticketService->getStatusLabels((int) $portal['projectId']);

        [$openTickets, $archivedTickets] = $this->partitionTickets($tickets, $statusLabels);

        $this->assignPortal($portal);
        $this->tpl->assign('statusLabels', $statusLabels);
        $this->tpl->assign('openTickets', $openTickets);
        $this->tpl->assign('archivedTickets', $archivedTickets);
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());

        return $this->tpl->display('supportportal.tickets', 'supportportal');
    }

    public function new(array $params): Response
    {
        $portal = $this->resolvePortalOrRedirect();
        if ($portal instanceof Response) {
            return $portal;
        }

        if ($this->incomingRequest->isMethod('POST')) {
            $ticketId = $this->ticketService->addTicket([
                'headline' => trim($params['headline'] ?? ''),
                'description' => trim($params['description'] ?? ''),
                'projectId' => (int) $portal['projectId'],
                'priority' => $params['priority'] ?? 2,
                'tags' => $portal['defaultTags'],
                'type' => 'task',
                'status' => 3,
                'editorId' => '',
            ]);

            if (is_array($ticketId)) {
                $this->tpl->setNotification($ticketId['msg'] ?? 'Could not create support ticket.', $ticketId['type'] ?? 'error');

                return Frontcontroller::redirect($this->supportUrl('/support/tickets/new'));
            }

            if ($ticketId === false) {
                $this->tpl->setNotification('Could not create support ticket.', 'error');

                return Frontcontroller::redirect($this->supportUrl('/support/tickets/new'));
            }

            $this->tpl->setNotification('Support ticket created.', 'success');

            return Frontcontroller::redirect($this->supportUrl('/support/tickets/'.$ticketId));
        }

        $this->assignPortal($portal);
        $this->tpl->assign('priorities', $this->ticketService->getPriorityLabels());

        return $this->tpl->display('supportportal.newTicket', 'supportportal');
    }

    public function show(array $params): Response
    {
        $portal = $this->resolvePortalOrRedirect();
        if ($portal instanceof Response) {
            return $portal;
        }

        $ticketId = (int) ($params['id'] ?? 0);
        $ticket = $this->supportTickets->getPortalTicket((int) $portal['projectId'], (int) session('userdata.id'), $ticketId);

        if ($ticket === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        if ($this->incomingRequest->isMethod('POST')) {
            $text = trim($params['text'] ?? '');

            if ($text === '') {
                $this->tpl->setNotification('Comment text is required.', 'error');

                return Frontcontroller::redirect($this->supportUrl('/support/tickets/'.$ticketId));
            }

            $commentAdded = $this->commentService->addComment([
                'text' => $text,
                'father' => 0,
            ], 'ticket', $ticketId, $ticket);

            if (! $commentAdded) {
                $this->tpl->setNotification('Could not add comment.', 'error');

                return Frontcontroller::redirect($this->supportUrl('/support/tickets/'.$ticketId));
            }

            $this->tpl->setNotification('Comment added.', 'success');

            return Frontcontroller::redirect($this->supportUrl('/support/tickets/'.$ticketId));
        }

        $statusLabels = $this->ticketService->getStatusLabels((int) $portal['projectId']);
        $comments = $this->commentService->getComments('ticket', $ticketId);

        $this->assignPortal($portal);
        $this->tpl->assign('ticket', $ticket);
        $this->tpl->assign('comments', $comments);
        $this->tpl->assign('statusLabels', $statusLabels);

        return $this->tpl->display('supportportal.showTicket', 'supportportal');
    }

    private function resolvePortalOrRedirect(): array|Response
    {
        $portal = $this->portalResolver->resolveCurrentHost($this->incomingRequest->getHost());
        if ($portal === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        if (! session()->exists('userdata.id')) {
            return Frontcontroller::redirect($this->supportUrl('/support/login'));
        }

        if (! $this->portalAccess->ensurePortalSession($portal)) {
            return $this->tpl->display('errors.error403', responseCode: 403);
        }

        return $portal;
    }
    private function partitionTickets(array $tickets, array $statusLabels): array
    {
        $openTickets = [];
        $archivedTickets = [];

        foreach ($tickets as $ticket) {
            $statusType = strtoupper((string) ($statusLabels[$ticket->status]['statusType'] ?? ''));

            if (in_array($statusType, ['DONE', 'CLOSED'], true)) {
                $archivedTickets[] = $ticket;
            } else {
                $openTickets[] = $ticket;
            }
        }

        return [$openTickets, $archivedTickets];
    }
}
