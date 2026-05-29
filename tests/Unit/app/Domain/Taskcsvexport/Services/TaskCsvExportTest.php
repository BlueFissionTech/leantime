<?php

namespace Unit\app\Domain\Taskcsvexport\Services;

use Leantime\Domain\Taskcsvexport\Services\TaskCsvExport;
use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Domain\Users\Services\Users as UserService;
use Unit\TestCase;

class TaskCsvExportTest extends TestCase
{
    public function test_build_rows_maps_requested_columns_and_fallbacks(): void
    {
        $ticketService = $this->createMock(TicketService::class);
        $userService = $this->createMock(UserService::class);

        $ticketService->method('prepareTicketSearchArray')
            ->willReturn(['currentProject' => 1, 'excludeType' => 'milestone']);
        $ticketService->method('getAll')
            ->willReturn([
                [
                    'headline' => 'Ship launch checklist',
                    'editorId' => '7',
                    'editorFirstname' => 'Jane',
                    'editorLastname' => 'Doe',
                    'dateToFinish' => '2026-03-20 04:00:00',
                    'milestoneHeadline' => 'Launch',
                    'clientName' => 'Morpro',
                    'projectName' => 'PM',
                    'priority' => 2,
                ],
                [
                    'headline' => 'Backfill docs',
                    'editorId' => '',
                    'editorFirstname' => '',
                    'editorLastname' => '',
                    'dateToFinish' => '',
                    'milestoneHeadline' => '',
                    'clientName' => '',
                    'projectName' => '',
                    'priority' => '',
                ],
            ]);
        $ticketService->method('getPriorityLabels')
            ->willReturn([2 => 'High']);

        $userService->method('getAll')
            ->willReturn([
                ['id' => 7, 'department' => 'Operations'],
            ]);

        $service = new TaskCsvExport($ticketService, $userService);
        $rows = $service->buildRows([]);

        $this->assertSame('Ship launch checklist', $rows[0]['task']);
        $this->assertSame('Operations', $rows[0]['department']);
        $this->assertSame('Jane Doe', $rows[0]['assignee']);
        $this->assertSame('2026-03-20', $rows[0]['dueDate']);
        $this->assertSame('Launch', $rows[0]['productOrMilestone']);
        $this->assertSame('High', $rows[0]['priority']);

        $this->assertSame('No Department', $rows[1]['department']);
        $this->assertSame('Unassigned', $rows[1]['assignee']);
        $this->assertSame('No Due Date', $rows[1]['dueDate']);
        $this->assertSame('No Product or Milestone', $rows[1]['productOrMilestone']);
        $this->assertSame('No Priority', $rows[1]['priority']);
    }

    public function test_build_csv_emits_header_and_rows(): void
    {
        $ticketService = $this->createMock(TicketService::class);
        $userService = $this->createMock(UserService::class);

        $ticketService->method('prepareTicketSearchArray')->willReturn([]);
        $ticketService->method('getAll')->willReturn([
            [
                'headline' => 'Alpha',
                'editorId' => '5',
                'editorFirstname' => 'Sam',
                'editorLastname' => 'Lee',
                'dateToFinish' => '2026-03-30 12:00:00',
                'milestoneHeadline' => '',
                'clientName' => 'Morpro',
                'projectName' => 'PM',
                'priority' => 1,
            ],
        ]);
        $ticketService->method('getPriorityLabels')->willReturn([1 => 'Critical']);
        $userService->method('getAll')->willReturn([
            ['id' => 5, 'department' => 'Delivery'],
        ]);

        $service = new TaskCsvExport($ticketService, $userService);
        $csv = $service->buildCsv([]);

        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        $header = str_getcsv($lines[0]);
        $row = str_getcsv($lines[1]);

        $this->assertSame(
            ['Task', 'Department', 'Assignee', 'Due Date', 'Product / Milestone', 'Priority'],
            $header
        );
        $this->assertSame(
            ['Alpha', 'Delivery', 'Sam Lee', '2026-03-30', 'Morpro', 'Critical'],
            $row
        );
    }
}
