<?php

namespace Leantime\Domain\Raci\Services;

use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;

class RaciAssignments
{
    public const ROLE_KEYS = ['responsible', 'accountable', 'consulted', 'informed'];

    public function __construct(
        private SettingRepository $settingsRepo,
        private TicketRepository $ticketRepository,
    ) {}

    public function saveProjectAssignments(int $projectId, array $values): void
    {
        $this->saveAssignments($this->projectKey($projectId), $values);
    }

    public function saveMilestoneAssignments(int $milestoneId, array $values): void
    {
        $this->saveAssignments($this->milestoneKey($milestoneId), $values);
    }

    public function saveTicketAssignments(int $ticketId, array $values): void
    {
        $this->saveAssignments($this->ticketKey($ticketId), $values);
    }

    public function getProjectAssignments(int $projectId): array
    {
        return $this->readAssignments($this->projectKey($projectId));
    }

    public function getMilestoneAssignments(int $milestoneId): array
    {
        return $this->readAssignments($this->milestoneKey($milestoneId));
    }

    public function getTicketAssignments(int $ticketId): array
    {
        return $this->readAssignments($this->ticketKey($ticketId));
    }

    public function resolveForMilestone(array|object $milestone): array
    {
        $projectId = $this->extractInt($milestone, 'projectId');
        $milestoneId = $this->extractInt($milestone, 'id');

        $resolved = $this->withSource($this->getProjectAssignments($projectId), 'project');
        $resolved = $this->overlay(
            $resolved,
            $this->withSource($this->getMilestoneAssignments($milestoneId), 'milestone')
        );

        return $resolved;
    }

    public function resolveForTicket(array|object $ticket): array
    {
        $projectId = $this->extractInt($ticket, 'projectId');
        $milestoneId = $this->extractInt($ticket, 'milestoneid');
        $ticketId = $this->extractInt($ticket, 'id');
        $dependingTicketId = $this->extractInt($ticket, 'dependingTicketId');
        $type = strtolower((string) $this->extractValue($ticket, 'type'));

        $resolved = $this->withSource($this->getProjectAssignments($projectId), 'project');

        if ($milestoneId > 0) {
            $resolved = $this->overlay(
                $resolved,
                $this->withSource($this->getMilestoneAssignments($milestoneId), 'milestone')
            );
        }

        if ($type === 'subtask' && $dependingTicketId > 0) {
            $resolved = $this->overlay(
                $resolved,
                $this->withSource($this->getTicketAssignments($dependingTicketId), 'parent_task')
            );
        }

        if ($ticketId > 0) {
            $resolved = $this->overlay(
                $resolved,
                $this->withSource($this->getTicketAssignments($ticketId), 'task')
            );
        }

        return $resolved;
    }

    public function toDisplayAssignments(array $resolvedAssignments, array $users): array
    {
        $userMap = [];
        foreach ($users as $user) {
            $id = (int) ($user['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $userMap[$id] = trim((string) ($user['firstname'] ?? '').' '.(string) ($user['lastname'] ?? ''));
        }

        $display = [];
        foreach (self::ROLE_KEYS as $roleKey) {
            $ids = array_values(array_filter(
                array_map('intval', (array) ($resolvedAssignments[$roleKey] ?? [])),
                static fn (int $id): bool => $id > 0
            ));

            $display[$roleKey] = [
                'ids' => $ids,
                'names' => array_values(array_filter(array_map(
                    static fn (int $id) => $userMap[$id] ?? null,
                    $ids
                ))),
                'source' => (string) ($resolvedAssignments['_sources'][$roleKey] ?? 'none'),
            ];
        }

        return $display;
    }

    private function saveAssignments(string $settingKey, array $values): void
    {
        $normalized = $this->normalizeAssignments($values);

        if ($this->isEmptyAssignments($normalized)) {
            $this->settingsRepo->deleteSetting($settingKey);

            return;
        }

        $this->settingsRepo->saveSetting($settingKey, json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    private function readAssignments(string $settingKey): array
    {
        $raw = $this->settingsRepo->getSetting($settingKey, false);
        if (! is_string($raw) || trim($raw) === '') {
            return $this->emptyAssignments();
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $this->emptyAssignments();
        }

        return $this->normalizeAssignments($decoded);
    }

    private function normalizeAssignments(array $values): array
    {
        return [
            'responsible' => $this->normalizeRole($values['raciResponsible'] ?? $values['responsible'] ?? []),
            'accountable' => $this->normalizeRole($values['raciAccountable'] ?? $values['accountable'] ?? []),
            'consulted' => $this->normalizeRole($values['raciConsulted'] ?? $values['consulted'] ?? []),
            'informed' => $this->normalizeRole($values['raciInformed'] ?? $values['informed'] ?? []),
        ];
    }

    private function normalizeRole(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === '' || $value === null ? [] : [$value];
        }

        $normalized = [];
        foreach ($value as $candidate) {
            $candidate = (int) $candidate;
            if ($candidate > 0 && ! in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }

    private function emptyAssignments(): array
    {
        return [
            'responsible' => [],
            'accountable' => [],
            'consulted' => [],
            'informed' => [],
        ];
    }

    private function withSource(array $assignments, string $source): array
    {
        $assignments['_sources'] = [];
        foreach (self::ROLE_KEYS as $roleKey) {
            if (! empty($assignments[$roleKey])) {
                $assignments['_sources'][$roleKey] = $source;
            }
        }

        return $assignments;
    }

    private function overlay(array $base, array $overlay): array
    {
        foreach (self::ROLE_KEYS as $roleKey) {
            if (! empty($overlay[$roleKey])) {
                $base[$roleKey] = $overlay[$roleKey];
                $base['_sources'][$roleKey] = $overlay['_sources'][$roleKey] ?? 'none';
            } elseif (! isset($base['_sources'][$roleKey])) {
                $base['_sources'][$roleKey] = 'none';
            }
        }

        return $base;
    }

    private function isEmptyAssignments(array $assignments): bool
    {
        foreach (self::ROLE_KEYS as $roleKey) {
            if (! empty($assignments[$roleKey])) {
                return false;
            }
        }

        return true;
    }

    private function projectKey(int $projectId): string
    {
        return 'projectsettings.'.$projectId.'.raciAssignments';
    }

    private function milestoneKey(int $milestoneId): string
    {
        return 'milestonesettings.'.$milestoneId.'.raciAssignments';
    }

    private function ticketKey(int $ticketId): string
    {
        return 'ticketsettings.'.$ticketId.'.raciAssignments';
    }

    private function extractInt(array|object $entity, string $key): int
    {
        return (int) $this->extractValue($entity, $key);
    }

    private function extractValue(array|object $entity, string $key): mixed
    {
        if (is_array($entity)) {
            return $entity[$key] ?? null;
        }

        return $entity->$key ?? null;
    }
}
