<?php

namespace Unit\app\Domain\Tickets\Support;

require_once __DIR__.'/../../../../../../app/Domain/Tickets/Support/ProjectSheetExportFormatter.php';

use Carbon\CarbonImmutable;
use Leantime\Domain\Tickets\Support\ProjectSheetExportFormatter;
use Unit\TestCase;

class ProjectSheetExportFormatterTest extends TestCase
{
    public function test_it_formats_ticket_rows_for_sheet_sync(): void
    {
        $formatter = new ProjectSheetExportFormatter;

        $rows = $formatter->format([
            [
                'id' => 101,
                'headline' => '12.4 Task title',
                'milestoneid' => 9,
                'milestoneHeadline' => '2.1 Milestone title',
                'status' => '4',
                'statusLabel' => 'In Progress',
                'tags' => 'approval:approved, gat:2026-03-31',
                'modified' => '2026-03-23 10:00:00',
                'projectId' => 26,
            ],
        ]);

        $this->assertSame([[
            'todoId' => 101,
            'todoTitle' => '12.4 Task title',
            'normalizedTodoNumber' => '12.4',
            'milestoneId' => 9,
            'milestoneTitle' => '2.1 Milestone title',
            'normalizedMilestoneNumber' => '2.1',
            'statusCode' => '4',
            'statusLabel' => 'In Progress',
            'approval' => 'approved',
            'gat' => '2026-03-31',
            'modified' => '2026-03-23 10:00:00',
            'projectId' => 26,
        ]], $rows);
    }

    public function test_it_honors_updated_since_filter(): void
    {
        $formatter = new ProjectSheetExportFormatter;

        $rows = $formatter->format([
            [
                'id' => 101,
                'headline' => '12.4 Task title',
                'milestoneid' => null,
                'milestoneHeadline' => '',
                'status' => '4',
                'statusLabel' => 'In Progress',
                'tags' => '',
                'modified' => '2026-03-23 10:00:00',
                'projectId' => 26,
            ],
            [
                'id' => 102,
                'headline' => '12.5 Later task',
                'milestoneid' => null,
                'milestoneHeadline' => '',
                'status' => '4',
                'statusLabel' => 'In Progress',
                'tags' => '',
                'modified' => '2026-03-23 12:00:00',
                'projectId' => 26,
            ],
        ], CarbonImmutable::parse('2026-03-23 11:00:00'));

        $this->assertCount(1, $rows);
        $this->assertSame(102, $rows[0]['todoId']);
    }
}
