<?php

namespace Unit\app\Domain\Tickets\Templates;

use Unit\TestCase;

class ShowKanbanTemplateTest extends TestCase
{
    public function test_kanban_template_renders_blocked_badge_for_blocked_tickets(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showKanbanV2.tpl.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('isTicketBlocked((int) $row[\'id\'], $tpl->get(\'allTicketStates\'))', $template);
        $this->assertStringContainsString('<span class="label label-important">Blocked</span>', $template);
    }
}
