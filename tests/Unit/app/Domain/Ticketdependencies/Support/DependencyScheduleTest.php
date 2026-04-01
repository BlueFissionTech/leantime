<?php

namespace Unit\app\Domain\Ticketdependencies\Support;

require_once __DIR__.'/../../../../../../app/Domain/Ticketdependencies/Support/DependencySchedule.php';

use Leantime\Domain\Ticketdependencies\Support\DependencySchedule;
use Unit\TestCase;

class DependencyScheduleTest extends TestCase
{
    public function test_it_uses_the_latest_predecessor_finish_date(): void
    {
        $schedule = new DependencySchedule;

        $latestFinish = $schedule->resolveLatestPredecessorFinish([
            ['editTo' => '2026-03-25 14:00:00', 'dateToFinish' => '2026-03-26 18:00:00'],
            ['editTo' => '', 'dateToFinish' => '2026-03-27 10:30:00'],
        ]);

        $this->assertSame('2026-03-27 10:30:00', $latestFinish?->format('Y-m-d H:i:s'));
    }

    public function test_it_detects_when_planned_start_is_before_predecessor_finish(): void
    {
        $schedule = new DependencySchedule;
        $latestFinish = $schedule->resolveLatestPredecessorFinish([
            ['editTo' => '2026-03-27 10:30:00', 'dateToFinish' => ''],
        ]);

        $this->assertTrue($schedule->violatesPlannedStart('2026-03-27 09:00:00', $latestFinish));
        $this->assertFalse($schedule->violatesPlannedStart('2026-03-27 10:30:00', $latestFinish));
    }

    public function test_it_can_shift_start_end_and_due_dates_forward(): void
    {
        $schedule = new DependencySchedule;
        $aligned = $schedule->alignSchedule([
            'editFrom' => '2026-03-27 09:00:00',
            'editTo' => '2026-03-27 17:00:00',
            'dateToFinish' => '2026-03-28 17:00:00',
        ], $schedule->resolveLatestPredecessorFinish([
            ['editTo' => '2026-03-28 09:00:00', 'dateToFinish' => ''],
        ]));

        $this->assertSame('2026-03-28 09:00:00', $aligned['editFrom']);
        $this->assertSame('2026-03-28 17:00:00', $aligned['editTo']);
        $this->assertSame('2026-03-29 17:00:00', $aligned['dateToFinish']);
    }
}
