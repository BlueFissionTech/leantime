<?php

namespace Leantime\Domain\Ticketdependencies\Support;

use Carbon\CarbonImmutable;

class DependencySchedule
{
    public function resolveLatestPredecessorFinish(array $dependencies): ?CarbonImmutable
    {
        $latestFinish = null;

        foreach ($dependencies as $dependency) {
            $finish = $this->resolveDependencyFinish($dependency);

            if ($finish === null) {
                continue;
            }

            if ($latestFinish === null || $finish->greaterThan($latestFinish)) {
                $latestFinish = $finish;
            }
        }

        return $latestFinish;
    }

    public function violatesPlannedStart(?string $plannedStart, ?CarbonImmutable $latestFinish): bool
    {
        if ($latestFinish === null || $plannedStart === null || $plannedStart === '') {
            return false;
        }

        return CarbonImmutable::parse($plannedStart)->lessThan($latestFinish);
    }

    public function alignSchedule(array $values, CarbonImmutable $latestFinish): array
    {
        $currentStart = ! empty($values['editFrom']) ? CarbonImmutable::parse($values['editFrom']) : null;

        if ($currentStart === null) {
            $values['editFrom'] = $latestFinish->format('Y-m-d H:i:s');

            return $values;
        }

        if ($currentStart->greaterThanOrEqualTo($latestFinish)) {
            return $values;
        }

        $shiftSeconds = $latestFinish->getTimestamp() - $currentStart->getTimestamp();
        $values['editFrom'] = $latestFinish->format('Y-m-d H:i:s');

        foreach (['editTo', 'dateToFinish'] as $dateField) {
            if (empty($values[$dateField])) {
                continue;
            }

            $values[$dateField] = CarbonImmutable::parse($values[$dateField])
                ->addSeconds($shiftSeconds)
                ->format('Y-m-d H:i:s');
        }

        return $values;
    }

    private function resolveDependencyFinish(array $dependency): ?CarbonImmutable
    {
        foreach (['editTo', 'dateToFinish'] as $field) {
            $value = $dependency[$field] ?? null;

            if (! $this->isValidDateValue($value)) {
                continue;
            }

            return CarbonImmutable::parse($value);
        }

        return null;
    }

    private function isValidDateValue(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return ! in_array($value, ['0000-00-00 00:00:00', '1969-12-31 00:00:00'], true);
    }
}
