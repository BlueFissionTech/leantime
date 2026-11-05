<?php

namespace Unit\app\Domain\Tickets\Services;

use Unit\TestCase;

class HighImpactNextAssignmentsSourceTest extends TestCase
{
    public function test_high_impact_assignments_exclude_subtasks_from_candidate_query(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("'excludeType' => 'milestone,subtask'", $source);
    }
}
