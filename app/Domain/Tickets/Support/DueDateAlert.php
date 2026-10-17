<?php

namespace Leantime\Domain\Tickets\Support;

use Carbon\CarbonImmutable;

class DueDateAlert
{
    private const INVALID_DUE_DATES = [
        '',
        '0000-00-00 00:00:00',
        '1969-12-31 00:00:00',
    ];

    public function forDate(?string $dateToFinish, ?CarbonImmutable $now = null): ?string
    {
        if ($dateToFinish === null || in_array($dateToFinish, self::INVALID_DUE_DATES, true)) {
            return null;
        }

        try {
            $dueDate = CarbonImmutable::parse($dateToFinish);
        } catch (\Throwable) {
            return null;
        }

        $today = ($now ?? CarbonImmutable::now())->startOfDay();

        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        if ($dueDate->lte($today->addDays(3)->endOfDay())) {
            return 'dueSoon';
        }

        return null;
    }
}
