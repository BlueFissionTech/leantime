<?php

namespace Unit\app\Domain\Tickets\Js;

use Unit\TestCase;

class TicketsControllerSourceTest extends TestCase
{
    public function test_all_project_search_clears_project_specific_filters(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Js/ticketsController.js');

        $this->assertIsString($source);
        $this->assertStringContainsString('if (project === "all") {', $source);
        $this->assertStringContainsString('milestones = "";', $source);
        $this->assertStringContainsString('sprints = "";', $source);
    }
}
