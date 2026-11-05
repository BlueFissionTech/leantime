<?php

namespace Unit\app\Domain\Tickets\Repositories;

use Unit\TestCase;

class TicketsStrictDatetimeSourceTest extends TestCase
{
    public function test_ticket_repository_uses_text_casts_for_strict_datetime_guards(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Repositories/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("\$columnText = \$this->dbHelper->castAs(\$column, 'text');", $source);
        $this->assertStringContainsString("\$editFromText = \$this->dbHelper->castAs(\$this->dbHelper->wrapColumn('zp_tickets.editFrom'), 'text');", $source);
        $this->assertStringContainsString("\$editToText = \$this->dbHelper->castAs(\$this->dbHelper->wrapColumn('zp_tickets.editTo'), 'text');", $source);
        $this->assertStringContainsString('{$editFromText} BETWEEN ? AND ?', $source);
        $this->assertStringContainsString('{$editToText} BETWEEN ? AND ?', $source);
        $this->assertStringNotContainsString('{$column} = \'\'', $source);
    }
}
