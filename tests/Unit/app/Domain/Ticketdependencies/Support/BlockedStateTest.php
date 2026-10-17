<?php

namespace Unit\app\Domain\Ticketdependencies\Support;

require_once __DIR__.'/../../../../../../app/Domain/Ticketdependencies/Support/BlockedState.php';

use Leantime\Domain\Ticketdependencies\Support\BlockedState;
use Unit\TestCase;

class BlockedStateTest extends TestCase
{
    public function test_it_marks_ticket_blocked_when_any_predecessor_is_not_done(): void
    {
        $blockedState = new BlockedState;

        $this->assertTrue($blockedState->isBlocked(['DONE', 'INPROGRESS']));
        $this->assertFalse($blockedState->isBlocked(['DONE', 'DONE']));
    }

    public function test_it_only_restricts_active_or_done_status_changes(): void
    {
        $blockedState = new BlockedState;
        $statusLabels = [
            1 => ['name' => 'status.blocked', 'statusType' => 'INPROGRESS'],
            3 => ['name' => 'status.new', 'statusType' => 'NEW'],
            4 => ['name' => 'status.in_progress', 'statusType' => 'INPROGRESS'],
            0 => ['name' => 'status.done', 'statusType' => 'DONE'],
        ];

        $this->assertFalse($blockedState->shouldRestrictStatusChange(1, $statusLabels));
        $this->assertFalse($blockedState->shouldRestrictStatusChange(3, $statusLabels));
        $this->assertTrue($blockedState->shouldRestrictStatusChange(4, $statusLabels));
        $this->assertTrue($blockedState->shouldRestrictStatusChange(0, $statusLabels));
    }

    public function test_it_resolves_the_blocked_status_id_from_status_labels(): void
    {
        $blockedState = new BlockedState;

        $this->assertSame(1, $blockedState->resolveBlockedStatusId([
            3 => ['name' => 'status.new'],
            1 => ['name' => 'status.blocked'],
            0 => ['name' => 'status.done'],
        ]));
    }
}
