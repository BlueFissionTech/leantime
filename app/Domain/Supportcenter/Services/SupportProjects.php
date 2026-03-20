<?php

namespace Leantime\Domain\Supportcenter\Services;

use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;

class SupportProjects
{
    public function __construct(
        private ProjectRepository $projectRepository,
    ) {}

    public function getAccessibleProjectsForUser(int $userId, ?int $clientId = null): array
    {
        $projects = $this->projectRepository->getUserProjects(
            userId: $userId,
            projectStatus: 'open',
            clientId: null,
            accessStatus: 'all'
        );

        $projects = is_array($projects) ? $projects : [];
        $supportProjects = $this->filterSupportProjects($projects);

        if (count($supportProjects) === 0 && $clientId !== null && $clientId > 0) {
            $clientProjects = $this->projectRepository->getClientProjects($clientId);
            $supportProjects = $this->filterSupportProjects(is_array($clientProjects) ? $clientProjects : []);
        }

        $unique = [];
        foreach ($supportProjects as $project) {
            $unique[(int) $project['id']] = $project;
        }

        return array_values($unique);
    }

    public function getSelectedProject(array $projects, ?int $requestedProjectId): array|false
    {
        foreach ($projects as $project) {
            if ((int) $project['id'] === (int) $requestedProjectId && $requestedProjectId !== null && $requestedProjectId > 0) {
                return $project;
            }
        }

        return $projects[0] ?? false;
    }

    public function ensureProjectAccess(int $userId, int $projectId, ?int $clientId = null): bool
    {
        if ($this->projectRepository->isUserAssignedToProject($userId, $projectId)) {
            return true;
        }

        $project = $this->projectRepository->getProject($projectId);
        if (! is_array($project)) {
            return false;
        }

        $projectClientId = (int) ($project['clientId'] ?? 0);
        $projectVisibility = (string) ($project['psettings'] ?? '');

        if ($projectVisibility === 'all') {
            $this->projectRepository->addProjectRelation($userId, $projectId, 10);

            return true;
        }

        if ($clientId !== null && $clientId > 0 && $projectClientId === $clientId) {
            $this->projectRepository->addProjectRelation($userId, $projectId, 10);

            return true;
        }

        return false;
    }

    private function filterSupportProjects(array $projects): array
    {
        return array_values(array_filter($projects, function ($project) {
            $name = strtolower((string) ($project['name'] ?? ''));

            return $name !== '' && (
                str_contains($name, 'support')
                || str_contains($name, 'help')
                || str_contains($name, 'service')
            );
        }));
    }
}
