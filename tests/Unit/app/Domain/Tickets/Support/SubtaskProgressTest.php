<?php

namespace Unit\app\Domain\Tickets\Support;

use Leantime\Domain\Tickets\Support\SubtaskProgress;
use PHPUnit\Framework\TestCase;

class SubtaskProgressTest extends TestCase
{
    public function test_sort_by_due_date_places_earliest_due_date_first_and_empty_dates_last(): void
    {
        $subtasks = [
            ['id' => 4, 'dateToFinish' => '', 'sortindex' => 0],
            ['id' => 3, 'dateToFinish' => '2026-03-20 00:00:00', 'sortindex' => 50],
            ['id' => 2, 'dateToFinish' => '2026-03-15 00:00:00', 'sortindex' => 20],
            ['id' => 1, 'dateToFinish' => '0000-00-00 00:00:00', 'sortindex' => 10],
        ];

        $sorted = SubtaskProgress::sortByDueDate($subtasks);

        $this->assertSame([2, 3, 4, 1], array_column($sorted, 'id'));
    }

    public function test_summarize_counts_completed_subtasks_only_from_done_status(): void
    {
        $summary = SubtaskProgress::summarize([
            ['status' => 0],
            ['status' => '0'],
            ['status' => 2],
            ['status' => -1],
        ]);

        $this->assertSame(['completed' => 2, 'total' => 4], $summary);
    }

    public function test_are_all_complete_requires_at_least_one_subtask_and_all_done(): void
    {
        $this->assertFalse(SubtaskProgress::areAllComplete([]));
        $this->assertFalse(SubtaskProgress::areAllComplete([
            ['status' => 0],
            ['status' => 4],
        ]));
        $this->assertTrue(SubtaskProgress::areAllComplete([
            ['status' => 0],
            ['status' => '0'],
        ]));
    }
}
