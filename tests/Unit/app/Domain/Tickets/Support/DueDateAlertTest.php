<?php

namespace Unit\app\Domain\Tickets\Support;

use Carbon\CarbonImmutable;
use Leantime\Domain\Tickets\Support\DueDateAlert;
use Unit\TestCase;

class DueDateAlertTest extends TestCase
{
    public function test_returns_overdue_for_past_due_dates(): void
    {
        $alert = new DueDateAlert();

        $result = $alert->forDate('2026-03-10 12:00:00', CarbonImmutable::parse('2026-03-13 09:00:00'));

        $this->assertSame('overdue', $result);
    }

    public function test_returns_due_soon_for_dates_within_three_days(): void
    {
        $alert = new DueDateAlert();

        $result = $alert->forDate('2026-03-16 17:00:00', CarbonImmutable::parse('2026-03-13 09:00:00'));

        $this->assertSame('dueSoon', $result);
    }

    public function test_returns_null_for_blank_or_placeholder_due_dates(): void
    {
        $alert = new DueDateAlert();
        $now = CarbonImmutable::parse('2026-03-13 09:00:00');

        $this->assertNull($alert->forDate(null, $now));
        $this->assertNull($alert->forDate('', $now));
        $this->assertNull($alert->forDate('0000-00-00 00:00:00', $now));
        $this->assertNull($alert->forDate('1969-12-31 00:00:00', $now));
    }

    public function test_returns_null_for_dates_more_than_three_days_out(): void
    {
        $alert = new DueDateAlert();

        $result = $alert->forDate('2026-03-20 09:00:00', CarbonImmutable::parse('2026-03-13 09:00:00'));

        $this->assertNull($result);
    }
}
