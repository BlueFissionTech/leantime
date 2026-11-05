<?php

namespace Leantime\Domain\Tickets\Support;

class KanbanTicketOrder
{
    private const INVALID_DUE_DATES = [
        '',
        '0000-00-00 00:00:00',
        '1969-12-31 00:00:00',
        null,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     * @return array<int, array<string, mixed>>
     */
    public function sortByDueDate(array $tickets): array
    {
        usort($tickets, function (array $left, array $right): int {
            $leftTimestamp = $this->toSortableTimestamp($left['dateToFinish'] ?? null);
            $rightTimestamp = $this->toSortableTimestamp($right['dateToFinish'] ?? null);

            if ($leftTimestamp === $rightTimestamp) {
                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            }

            return $leftTimestamp <=> $rightTimestamp;
        });

        return $tickets;
    }

    private function toSortableTimestamp(mixed $dateToFinish): int
    {
        if (! is_string($dateToFinish) || in_array($dateToFinish, self::INVALID_DUE_DATES, true)) {
            return PHP_INT_MAX;
        }

        $timestamp = strtotime($dateToFinish);

        return $timestamp === false ? PHP_INT_MAX : $timestamp;
    }
}
