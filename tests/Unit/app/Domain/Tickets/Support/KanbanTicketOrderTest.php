<?php

namespace Unit\app\Domain\Tickets\Support;

require_once __DIR__.'/../../../../../../app/Domain/Tickets/Support/KanbanTicketOrder.php';

use Leantime\Domain\Tickets\Support\KanbanTicketOrder;
use Unit\TestCase;

class KanbanTicketOrderTest extends TestCase
{
    public function test_it_sorts_tickets_by_due_date_with_undated_tickets_last(): void
    {
        $sorter = new KanbanTicketOrder();

        $sorted = $sorter->sortByDueDate([
            ['id' => 3, 'dateToFinish' => '0000-00-00 00:00:00'],
            ['id' => 2, 'dateToFinish' => '2026-03-29 00:00:00'],
            ['id' => 1, 'dateToFinish' => '2026-03-27 00:00:00'],
            ['id' => 4, 'dateToFinish' => null],
        ]);

        $this->assertSame([1, 2, 3, 4], array_column($sorted, 'id'));
    }

    public function test_it_uses_ticket_id_as_tiebreaker_for_matching_due_dates(): void
    {
        $sorter = new KanbanTicketOrder();

        $sorted = $sorter->sortByDueDate([
            ['id' => 12, 'dateToFinish' => '2026-03-29 00:00:00'],
            ['id' => 11, 'dateToFinish' => '2026-03-29 00:00:00'],
        ]);

        $this->assertSame([11, 12], array_column($sorted, 'id'));
    }
}
