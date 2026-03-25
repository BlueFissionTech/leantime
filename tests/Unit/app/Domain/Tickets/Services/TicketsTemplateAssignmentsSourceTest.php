<?php

namespace Unit\app\Domain\Tickets\Services;

use Unit\TestCase;

class TicketsTemplateAssignmentsSourceTest extends TestCase
{
    public function test_ticket_template_assignments_skip_project_specific_sprint_helpers_for_all_projects_scope(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("\$isAllProjectsScope = \$filterProjectId === '' || \$filterProjectId === null || \$filterProjectId === 'all';", $source);
        $this->assertStringContainsString("\$sprints = \$isAllProjectsScope ? [] : \$this->sprintService->getAllSprints(\$filterProjectId);", $source);
        $this->assertStringContainsString("\$futureSprints = \$isAllProjectsScope ? [] : \$this->sprintService->getAllFutureSprints((int) \$filterProjectId);", $source);
        $this->assertStringContainsString("'currentSprint' => \$isAllProjectsScope ? '' : (\$currentSprint ?: session('currentSprint')),", $source);
    }
}
