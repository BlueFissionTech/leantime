<?php

namespace Leantime\Domain\Ticketdependencies\Support;

class BlockedState
{
    public function isBlocked(array $predecessorStatusTypes): bool
    {
        foreach ($predecessorStatusTypes as $statusType) {
            if ($statusType !== 'DONE') {
                return true;
            }
        }

        return false;
    }

    public function shouldRestrictStatusChange(int|string|null $targetStatusId, array $statusLabels): bool
    {
        if ($targetStatusId === null || $targetStatusId === '') {
            return false;
        }

        $status = $statusLabels[$targetStatusId] ?? $statusLabels[(string) $targetStatusId] ?? null;

        if (! is_array($status)) {
            return false;
        }

        if (($status['name'] ?? '') === 'status.blocked') {
            return false;
        }

        return in_array($status['statusType'] ?? '', ['NEW', 'INPROGRESS', 'DONE'], true);
    }

    public function resolveBlockedStatusId(array $statusLabels): int
    {
        foreach ($statusLabels as $statusId => $status) {
            if (($status['name'] ?? '') === 'status.blocked') {
                return (int) $statusId;
            }
        }

        return 1;
    }
}
