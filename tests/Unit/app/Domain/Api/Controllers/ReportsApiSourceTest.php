<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class ReportsApiSourceTest extends TestCase
{
    public function test_reports_api_controller_supports_history_and_report_variants(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Reports.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("'full' =>", $controller);
        $this->assertStringContainsString("'realtime' =>", $controller);
        $this->assertStringContainsString("'backlog' =>", $controller);
        $this->assertStringContainsString("'sprint' =>", $controller);
        $this->assertStringContainsString("'projectStatusSummary' =>", $controller);
        $this->assertStringContainsString("'statusHistory' =>", $controller);
        $this->assertStringContainsString("'qualitative' => \$this->commentService->getComments('project', \$projectId, 1)", $controller);
    }
}
