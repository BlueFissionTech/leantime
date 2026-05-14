<?php

namespace Leantime\Domain\Raci\Services;

use Leantime\Domain\Notifications\Models\Notification;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;

class RaciNotificationRouting
{
    public function __construct(
        private RaciAssignments $raciAssignments,
        private TicketRepository $ticketRepository,
    ) {}

    public function resolveRecipients(Notification $notification): array
    {
        $assignments = $this->resolveAssignments($notification);

        if ($assignments === null || ! $this->hasAssignments($assignments)) {
            return [
                'hasScopedAssignments' => false,
                'cta' => [],
                'info' => [],
                'digest' => [],
                'cadence' => $this->suggestDigestCadence($notification),
            ];
        }

        return [
            'hasScopedAssignments' => true,
            'cta' => array_values(array_unique(array_merge(
                array_map('intval', $assignments['responsible'] ?? []),
                array_map('intval', $assignments['accountable'] ?? [])
            ))),
            'info' => array_values(array_unique(array_map('intval', $assignments['consulted'] ?? []))),
            'digest' => array_values(array_unique(array_map('intval', $assignments['informed'] ?? []))),
            'cadence' => $this->suggestDigestCadence($notification),
        ];
    }

    public function suggestDigestCadence(Notification $notification): string
    {
        return match ($notification->action) {
            'commented', 'status_changed' => 'daily',
            'created', 'updated' => $notification->module === 'projects' ? 'weekly' : 'daily',
            default => 'daily',
        };
    }

    private function resolveAssignments(Notification $notification): ?array
    {
        return match ($notification->module) {
            'projects' => $this->raciAssignments->getProjectAssignments((int) $notification->projectId),
            'tickets' => $this->resolveTicketLikeAssignments($notification->entity),
            'comments' => $this->resolveCommentAssignments($notification),
            default => null,
        };
    }

    private function resolveCommentAssignments(Notification $notification): ?array
    {
        $entity = $notification->entity;
        if (is_array($entity) && ($entity['contextModule'] ?? '') === 'project') {
            return $this->raciAssignments->getProjectAssignments((int) ($entity['contextId'] ?? $notification->projectId));
        }

        $contextId = is_array($entity) ? (int) ($entity['contextId'] ?? $entity['moduleId'] ?? 0) : 0;
        if ($contextId <= 0) {
            return null;
        }

        $ticket = $this->ticketRepository->getTicket($contextId);
        if (! $ticket) {
            return null;
        }

        return $this->resolveTicketLikeAssignments($ticket);
    }

    private function resolveTicketLikeAssignments(array|object|null $entity): ?array
    {
        if ($entity === null) {
            return null;
        }

        $type = strtolower((string) $this->extract($entity, 'type'));
        if ($type === 'milestone') {
            return $this->raciAssignments->resolveForMilestone($entity);
        }

        return $this->raciAssignments->resolveForTicket($entity);
    }

    private function hasAssignments(array $assignments): bool
    {
        foreach (RaciAssignments::ROLE_KEYS as $roleKey) {
            if (! empty($assignments[$roleKey])) {
                return true;
            }
        }

        return false;
    }

    private function extract(array|object $entity, string $key): mixed
    {
        if (is_array($entity)) {
            return $entity[$key] ?? null;
        }

        return $entity->$key ?? null;
    }
}
