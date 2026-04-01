<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class Comments extends Controller
{
    private CommentService $commentService;

    private ProjectService $projectService;

    private TicketService $ticketService;

    public function init(
        CommentService $commentService,
        ProjectService $projectService,
        TicketService $ticketService
    ): void {
        $this->commentService = $commentService;
        $this->projectService = $projectService;
        $this->ticketService = $ticketService;
    }

    public function get(array $params): Response
    {
        if (! isset($params['moduleId']) || ! in_array(($params['module'] ?? ''), ['ticket', 'project'], true)) {
            return $this->tpl->displayJson(['error' => 'module=ticket|project and moduleId are required'], 400);
        }

        $comments = $this->commentService->getComments((string) $params['module'], (int) $params['moduleId']) ?: [];

        return $this->tpl->displayJson(['result' => ['comments' => $comments]]);
    }

    public function post(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$editor)) {
            return $this->tpl->displayJson(['error' => 'Not Authorized'], 403);
        }

        if (! in_array(($params['module'] ?? ''), ['ticket', 'project'], true)) {
            return $this->tpl->displayJson(['error' => 'Only module=ticket or module=project is currently supported'], 400);
        }

        if (! isset($params['moduleId']) || trim((string) ($params['text'] ?? '')) === '') {
            return $this->tpl->displayJson(['error' => 'moduleId and text are required'], 400);
        }

        $module = (string) $params['module'];
        $moduleId = (int) $params['moduleId'];
        $entity = $this->resolveEntity($module, $moduleId);

        if (! $entity) {
            $label = $module === 'project' ? 'Project' : 'Ticket';

            return $this->tpl->displayJson(['error' => $label.' not found'], 404);
        }

        if ($module === 'project' && isset($params['status']) && ! in_array((string) $params['status'], ['green', 'yellow', 'red', ''], true)) {
            return $this->tpl->displayJson(['error' => 'status must be green, yellow, or red'], 400);
        }

        $createdComment = $this->commentService->createComment($params, $module, $moduleId, $entity);

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

    private function resolveEntity(string $module, int $moduleId): mixed
    {
        return match ($module) {
            'project' => $this->projectService->getProject($moduleId),
            'ticket' => $this->ticketService->getTicket($moduleId),
            default => false,
        };
    }
}
