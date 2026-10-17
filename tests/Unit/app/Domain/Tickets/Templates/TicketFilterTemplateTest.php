<?php

namespace Unit\app\Domain\Tickets\Templates;

use Unit\TestCase;

class TicketFilterTemplateTest extends TestCase
{
    public function test_ticket_filter_template_exposes_explicit_project_scope_control(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/submodules/ticketFilter.sub.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('id="projectScopeSelect"', $template);
        $this->assertStringContainsString('All accessible projects', $template);
        $this->assertStringContainsString('Cross-project search clears project-specific milestone and sprint filters.', $template);
    }

    public function test_ticket_filter_v2_template_exposes_explicit_project_scope_control(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/submodules/ticketFilterV2.sub.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('id="projectScopeSelect"', $template);
        $this->assertStringContainsString('All accessible projects', $template);
        $this->assertStringContainsString('Cross-project search clears project-specific milestone and sprint filters.', $template);
        $this->assertStringContainsString("jQuery('#projectScopeSelect').on('change'", $template);
    }
}
