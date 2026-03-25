<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class Comments extends Controller
{
    private CommentService $commentService;

    private TicketService $ticketService;

    public function init(
        CommentService $commentService,
        TicketService $ticketService
    ): void {
        $this->commentService = $commentService;
        $this->ticketService = $ticketService;
    }

    public function get(array $params): Response
    {
        if (($params['module'] ?? '') !== 'ticket' || ! isset($params['moduleId'])) {
            return $this->tpl->displayJson(['error' => 'module=ticket and moduleId are required'], 400);
        }

        $comments = $this->commentService->getComments('ticket', (int) $params['moduleId']) ?: [];

        return $this->tpl->displayJson(['result' => ['comments' => $comments]]);
    }

    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (($params['module'] ?? '') !== 'ticket') {
            return $this->tpl->displayJson(['error' => 'Only module=ticket is currently supported'], 400);
        }

        if (! isset($params['moduleId']) || trim((string) ($params['text'] ?? '')) === '') {
            return $this->tpl->displayJson(['error' => 'moduleId and text are required'], 400);
        }

        $ticketId = (int) $params['moduleId'];
        $ticket = $this->ticketService->getTicket($ticketId);

        if (! $ticket) {
            return $this->tpl->displayJson(['error' => 'Ticket not found'], 404);
        }

        $createdComment = $this->commentService->createComment($params, 'ticket', $ticketId, $ticket);

        if ($createdComment === false) {
            return $this->tpl->displayJson(['error' => 'Could not create comment'], 500);
        }

        return $this->tpl->displayJson(['status' => 'ok', 'result' => $createdComment], 201);
    }

    public function patch(array $params): Response
    {
        return $this->tpl->displayJson(['status' => 'Not implemented'], 501);
    }

    public function delete(array $params): Response
    {
        return $this->tpl->displayJson(['status' => 'Not implemented'], 501);
    }
}
