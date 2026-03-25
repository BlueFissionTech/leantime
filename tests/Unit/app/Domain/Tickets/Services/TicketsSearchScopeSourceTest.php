<?php

namespace Unit\app\Domain\Tickets\Services;

use Unit\TestCase;

class TicketsSearchScopeSourceTest extends TestCase
{
    public function test_prepare_ticket_search_array_maps_project_id_all_to_empty_project_scope(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("\$searchCriteria['currentProject'] = \$searchParams['projectId'] === 'all' ? '' : \$searchParams['projectId'];", $source);
    }

    public function test_prepare_ticket_search_array_clears_sprint_for_all_project_scope(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("if (\$searchCriteria['currentProject'] === '') {", $source);
        $this->assertStringContainsString("\$searchCriteria['sprint'] = '';", $source);
    }

    public function test_ticket_repository_only_applies_numeric_sprint_filters(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Repositories/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("preg_match('/^[0-9,]+\$/', (string) \$searchCriteria['sprint'])", $source);
    }
}
