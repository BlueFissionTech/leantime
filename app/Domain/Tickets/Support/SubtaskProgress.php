<?php

namespace Leantime\Domain\Tickets\Support;

class SubtaskProgress
{
    /**
     * @param  array<int, array<string, mixed>>  $subtasks
     * @return array<int, array<string, mixed>>
     */
    public static function sortByDueDate(array $subtasks): array
    {
        usort($subtasks, function (array $left, array $right): int {
            [$leftHasDueDate, $leftTimestamp] = self::extractDueDateSortValue($left['dateToFinish'] ?? null);
            [$rightHasDueDate, $rightTimestamp] = self::extractDueDateSortValue($right['dateToFinish'] ?? null);

            if ($leftHasDueDate !== $rightHasDueDate) {
                return $leftHasDueDate ? -1 : 1;
            }

            if ($leftHasDueDate && $leftTimestamp !== $rightTimestamp) {
                return $leftTimestamp <=> $rightTimestamp;
            }

            $leftSortIndex = (int) ($left['sortindex'] ?? 0);
            $rightSortIndex = (int) ($right['sortindex'] ?? 0);
            if ($leftSortIndex !== $rightSortIndex) {
                return $leftSortIndex <=> $rightSortIndex;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $subtasks;
    }

    /**
     * @param  array<int, array<string, mixed>>  $subtasks
     * @return array{completed: int, total: int}
     */
    public static function summarize(array $subtasks): array
    {
        $completed = 0;

        foreach ($subtasks as $subtask) {
            if (self::isComplete($subtask)) {
                $completed++;
            }
        }

        return [
            'completed' => $completed,
            'total' => count($subtasks),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $subtasks
     */
    public static function areAllComplete(array $subtasks): bool
    {
        if ($subtasks === []) {
            return false;
        }

        foreach ($subtasks as $subtask) {
            if (! self::isComplete($subtask)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: bool, 1: int}
     */
    private static function extractDueDateSortValue(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '' || $value === '0000-00-00 00:00:00' || $value === '1969-12-31 00:00:00') {
            return [false, PHP_INT_MAX];
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return [false, PHP_INT_MAX];
        }

        return [true, $timestamp];
    }

    /**
     * @param  array<string, mixed>  $subtask
     */
    private static function isComplete(array $subtask): bool
    {
        return (string) ($subtask['status'] ?? '') === '0';
    }
}
