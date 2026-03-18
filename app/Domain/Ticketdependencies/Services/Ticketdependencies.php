<?php

namespace Leantime\Domain\Ticketdependencies\Services;

use Leantime\Domain\Ticketdependencies\Repositories\Ticketdependencies as TicketdependenciesRepository;
use Leantime\Domain\Ticketdependencies\Support\BlockedState;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;

class Ticketdependencies
{
    public function __construct(
        private TicketdependenciesRepository $ticketdependencyRepository,
        private TicketRepository $ticketRepository,
        private BlockedState $blockedState
    ) {}

    public function getDependencyTicketIds(int $ticketId): array
    {
        return $this->ticketdependencyRepository->getDependencyTicketIds($ticketId);
    }

    public function getDependencies(int $ticketId): array
    {
        return $this->ticketdependencyRepository->getDependencies($ticketId);
    }

    public function syncDependencies(int $ticketId, array $dependencyTicketIds, int $createdBy): bool
    {
        $ticket = $this->ticketRepository->getTicket($ticketId);

        if (! $ticket) {
            return false;
        }

        $dependencyIds = $this->ticketdependencyRepository->getValidDependencyIds(
            $ticketId,
            (int) $ticket->projectId,
            $dependencyTicketIds
        );

        return $this->ticketdependencyRepository->syncDependencies($ticketId, $dependencyIds, $createdBy);
    }

    public function getBlockedTicketMap(array $ticketIds, array $statusLabels): array
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));

        if (empty($ticketIds)) {
            return [];
        }

        $statusTypes = [];
        foreach ($statusLabels as $statusId => $status) {
            $statusTypes[(string) $statusId] = $status['statusType'] ?? '';
        }

        $dependencyStatuses = $this->ticketdependencyRepository->getDependencyStatusesForTickets($ticketIds);
        $blockedMap = [];

        foreach ($ticketIds as $ticketId) {
            $predecessorTypes = array_map(
                fn ($statusId) => $statusTypes[(string) $statusId] ?? '',
                $dependencyStatuses[$ticketId] ?? []
            );
            $blockedMap[$ticketId] = $this->blockedState->isBlocked($predecessorTypes);
        }

        return $blockedMap;
    }

    public function isTicketBlocked(int $ticketId, array $statusLabels): bool
    {
        return $this->getBlockedTicketMap([$ticketId], $statusLabels)[$ticketId] ?? false;
    }

    public function coerceBlockedStatus(int $ticketId, int|string|null $targetStatusId, array $statusLabels): int|string|null
    {
        if (! $this->blockedState->shouldRestrictStatusChange($targetStatusId, $statusLabels)) {
            return $targetStatusId;
        }

        if (! $this->isTicketBlocked($ticketId, $statusLabels)) {
            return $targetStatusId;
        }

        return $this->blockedState->resolveBlockedStatusId($statusLabels);
    }
}
