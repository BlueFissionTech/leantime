<?php

namespace Leantime\Domain\Supportcenter\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth as AuthService;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Leantime\Domain\Supportcenter\Services\GithubElevation;
use Leantime\Domain\Supportcenter\Services\SupportProjects;
use Leantime\Domain\Supportportal\Repositories\SupportTickets;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Symfony\Component\HttpFoundation\Response;

class Support extends Controller
{
    private SupportProjects $supportProjects;

    private SupportTickets $supportTickets;

    private TicketService $ticketService;

    private CommentService $commentService;

    private ProjectService $projectService;

    private GithubElevation $githubElevation;

    public function init(
        SupportProjects $supportProjects,
        SupportTickets $supportTickets,
        TicketService $ticketService,
        CommentService $commentService,
        ProjectService $projectService,
        GithubElevation $githubElevation,
    ): void {
        $this->supportProjects = $supportProjects;
        $this->supportTickets = $supportTickets;
        $this->ticketService = $ticketService;
        $this->commentService = $commentService;
        $this->projectService = $projectService;
        $this->githubElevation = $githubElevation;
    }

    public function index(array $params): Response
    {
        [$projects, $project] = $this->resolveProjectsAndSelection();
        $ownOnly = ! AuthService::userIsAtLeast(Roles::$manager, true);

        if ($projects === []) {
            $this->assignCommon([], false, [], [], [], []);

            return $this->tpl->display('supportcenter.index');
        }

        $this->supportProjects->ensureProjectAccess((int) session('userdata.id'), (int) $project['id'], $this->getCurrentClientId());
        $this->projectService->changeCurrentSessionProject((int) $project['id']);

        $tickets = $this->supportTickets->getTicketsForProjects([(int) $project['id']], (int) session('userdata.id'), $ownOnly);
        $statusLabels = $this->ticketService->getStatusLabels((int) $project['id']);

        [$openTickets, $archivedTickets] = $this->partitionTickets($tickets, $statusLabels);

        $this->assignCommon($projects, $project, $statusLabels, $openTickets, $archivedTickets, $this->ticketService->getPriorityLabels());

        return $this->tpl->display('supportcenter.index');
    }

    public function new(array $params): Response
    {
        [$projects, $project] = $this->resolveProjectsAndSelection();

        if ($projects === []) {
            $this->tpl->setNotification('No support projects are available for your account.', 'error');

            return Frontcontroller::redirect(BASE_URL.'/support-center');
        }

        if ($this->incomingRequest->isMethod('POST')) {
            $payload = $this->incomingRequest->all();
            $selectedProject = $this->supportProjects->getSelectedProject($projects, (int) ($payload['projectId'] ?? $project['id']));

            if ($selectedProject === false) {
                $this->tpl->setNotification('Please choose a valid support project.', 'error');

                return Frontcontroller::redirect(BASE_URL.'/support-center/new');
            }

            $this->supportProjects->ensureProjectAccess((int) session('userdata.id'), (int) $selectedProject['id'], $this->getCurrentClientId());
            $this->projectService->changeCurrentSessionProject((int) $selectedProject['id']);

            $ticketId = $this->ticketService->addTicket([
                'headline' => trim($payload['headline'] ?? ''),
                'description' => trim($payload['description'] ?? ''),
                'projectId' => (int) $selectedProject['id'],
                'priority' => $payload['priority'] ?? 2,
                'tags' => 'support,internal',
                'type' => 'task',
                'status' => 3,
                'editorId' => '',
            ]);

            if (is_array($ticketId)) {
                $this->tpl->setNotification($ticketId['msg'] ?? 'Could not create support ticket.', $ticketId['type'] ?? 'error');

                return Frontcontroller::redirect(BASE_URL.'/support-center/new?projectId='.(int) $selectedProject['id']);
            }

            if ($ticketId === false) {
                $this->tpl->setNotification('Could not create support ticket.', 'error');

                return Frontcontroller::redirect(BASE_URL.'/support-center/new?projectId='.(int) $selectedProject['id']);
            }

            $this->tpl->setNotification('Support ticket created.', 'success');

            return Frontcontroller::redirect(BASE_URL.'/support-center/'.$ticketId.'?projectId='.(int) $selectedProject['id']);
        }

        $this->assignCommon($projects, $project, [], [], [], $this->ticketService->getPriorityLabels());

        return $this->tpl->display('supportcenter.new');
    }

    public function show(array $params): Response
    {
        [$projects, $project] = $this->resolveProjectsAndSelection();

        if ($projects === []) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $ownOnly = ! AuthService::userIsAtLeast(Roles::$manager, true);
        $projectIds = array_map(fn ($candidate) => (int) $candidate['id'], $projects);
        $ticketId = (int) ($params['id'] ?? 0);
        $ticket = $this->supportTickets->getTicketForProjects($projectIds, (int) session('userdata.id'), $ticketId, $ownOnly);

        if ($ticket === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $currentProject = $this->supportProjects->getSelectedProject($projects, (int) $ticket->projectId) ?: $project;
        $this->supportProjects->ensureProjectAccess((int) session('userdata.id'), (int) $ticket->projectId, $this->getCurrentClientId());
        $this->projectService->changeCurrentSessionProject((int) $ticket->projectId);

        if ($this->incomingRequest->isMethod('POST')) {
            $payload = $this->incomingRequest->all();
            $text = trim($payload['text'] ?? '');

            if ($text === '') {
                $this->tpl->setNotification('Comment text is required.', 'error');

                return Frontcontroller::redirect(BASE_URL.'/support-center/'.$ticketId.'?projectId='.(int) $ticket->projectId);
            }

            $commentAdded = $this->commentService->addComment([
                'text' => $text,
                'father' => 0,
            ], 'ticket', $ticketId, $ticket);

            if (! $commentAdded) {
                $this->tpl->setNotification('Could not add comment.', 'error');

                return Frontcontroller::redirect(BASE_URL.'/support-center/'.$ticketId.'?projectId='.(int) $ticket->projectId);
            }

            $this->tpl->setNotification('Comment added.', 'success');

            return Frontcontroller::redirect(BASE_URL.'/support-center/'.$ticketId.'?projectId='.(int) $ticket->projectId);
        }

        $statusLabels = $this->ticketService->getStatusLabels((int) $ticket->projectId);
        $comments = $this->commentService->getComments('ticket', $ticketId);

        $this->assignCommon($projects, $currentProject, $statusLabels, [], [], []);
        $this->tpl->assign('ticket', $ticket);
        $this->tpl->assign('comments', $comments);
        $this->tpl->assign('githubIssue', $this->githubElevation->getTicketGithubIssue($ticketId));
        $this->tpl->assign('githubStatus', $this->githubElevation->getTicketGithubStatus($ticketId));
        $this->tpl->assign('canElevateGitHub', AuthService::userIsAtLeast(Roles::$manager, true));
        $this->tpl->assign('defaultGithubTitle', $this->githubElevation->getDefaultGithubTitle($ticket));
        $this->tpl->assign('defaultGithubSummary', $this->githubElevation->getDefaultGithubSummary($ticket));

        return $this->tpl->display('supportcenter.show');
    }

    public function elevateGithub(array $params): Response
    {
        if (! AuthService::userIsAtLeast(Roles::$manager, true)) {
            return $this->tpl->display('errors.error403', responseCode: 403);
        }

        [$projects] = $this->resolveProjectsAndSelection();
        $projectIds = array_map(fn ($candidate) => (int) $candidate['id'], $projects);
        $ticketId = (int) ($params['id'] ?? 0);
        $ticket = $this->supportTickets->getTicketForProjects($projectIds, (int) session('userdata.id'), $ticketId, false);

        if ($ticket === false) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        $result = $this->githubElevation->createGithubIssue($ticketId, $ticket, $this->incomingRequest->all());

        $this->tpl->setNotification($result['message'], $result['ok'] ? 'success' : 'error');

        return Frontcontroller::redirect(BASE_URL.'/support-center/'.$ticketId.'?projectId='.(int) $ticket->projectId);
    }

    private function resolveProjectsAndSelection(): array
    {
        $userId = (int) session('userdata.id');
        $clientId = $this->getCurrentClientId();
        $projects = $this->supportProjects->getAccessibleProjectsForUser($userId, $clientId);
        $requestedProjectId = (int) ($this->incomingRequest->get('projectId') ?? 0);
        $project = $this->supportProjects->getSelectedProject($projects, $requestedProjectId);

        return [$projects, $project];
    }

    private function assignCommon(array $projects, array|false $project, array $statusLabels, array $openTickets, array $archivedTickets, array $priorities): void
    {
        $this->tpl->assign('supportProjects', $projects);
        $this->tpl->assign('selectedSupportProject', $project);
        $this->tpl->assign('statusLabels', $statusLabels);
        $this->tpl->assign('openTickets', $openTickets);
        $this->tpl->assign('archivedTickets', $archivedTickets);
        $this->tpl->assign('priorities', $priorities);
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

    private function getCurrentClientId(): ?int
    {
        $clientId = session('userdata.clientId');

        return is_numeric($clientId) ? (int) $clientId : null;
    }
}
