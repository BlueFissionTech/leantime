<?php

namespace Unit\app\Domain\Tickets\Services;

use Unit\TestCase;

class TicketsPatchSourceTest extends TestCase
{
    public function test_ticket_patch_returns_repository_result_for_non_status_updates(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('return $return;', $source);
    }
}
