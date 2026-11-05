<?php

namespace Unit\app\Domain\Tickets\Templates;

use Unit\TestCase;

class ShowAllTableTemplateTest extends TestCase
{
    public function test_table_views_wrap_ticket_table_in_scroll_container(): void
    {
        $showAll = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showAll.tpl.php');
        $showAllV2 = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showAllV2.tpl.php');

        $this->assertIsString($showAll);
        $this->assertIsString($showAllV2);
        $this->assertStringContainsString('<div class="ticket-table-scroll">', $showAll);
        $this->assertStringContainsString('<div class="ticket-table-scroll">', $showAllV2);
    }

    public function test_table_scroll_styles_enable_horizontal_overflow_for_ticket_tables(): void
    {
        $styles = file_get_contents(__DIR__.'/../../../../../../public/assets/css/components/style.default.css');

        $this->assertIsString($styles);
        $this->assertStringContainsString('.ticket-table-scroll {', $styles);
        $this->assertStringContainsString('overflow-x: auto;', $styles);
        $this->assertStringContainsString('.ticket-table-scroll .ticketTable {', $styles);
        $this->assertStringContainsString('min-width: 1280px;', $styles);
    }
}
