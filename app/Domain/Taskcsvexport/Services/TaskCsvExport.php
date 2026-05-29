<?php

namespace Leantime\Domain\Taskcsvexport\Services;

use Leantime\Domain\Tickets\Services\Tickets as TicketService;
use Leantime\Domain\Users\Services\Users as UserService;

class TaskCsvExport
{
    public function __construct(
        private TicketService $ticketService,
        private UserService $userService
    ) {}

    public function buildCsv(array $requestParams): string
    {
        $rows = $this->buildRows($requestParams);
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, [
            'Task',
            'Department',
            'Assignee',
            'Due Date',
            'Product / Milestone',
            'Priority',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['task'],
                $row['department'],
                $row['assignee'],
                $row['dueDate'],
                $row['productOrMilestone'],
                $row['priority'],
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<int, array{task:string, department:string, assignee:string, dueDate:string, productOrMilestone:string, priority:string}>
     */
    public function buildRows(array $requestParams): array
    {
        $searchCriteria = $this->ticketService->prepareTicketSearchArray($requestParams);
        $searchCriteria['excludeType'] = 'milestone';

        $tickets = $this->ticketService->getAll($searchCriteria) ?: [];
        $users = $this->indexUsersById($this->userService->getAll());
        $priorityLabels = $this->ticketService->getPriorityLabels();

        return array_map(function (array $ticket) use ($users, $priorityLabels): array {
            $assignee = trim(($ticket['editorFirstname'] ?? '').' '.($ticket['editorLastname'] ?? ''));
            $user = $users[(string) ($ticket['editorId'] ?? '')] ?? [];

            return [
                'task' => trim((string) ($ticket['headline'] ?? '')) ?: 'Untitled Task',
                'department' => trim((string) ($user['department'] ?? '')) ?: 'No Department',
                'assignee' => $assignee !== '' ? $assignee : 'Unassigned',
                'dueDate' => $this->formatDueDate((string) ($ticket['dateToFinish'] ?? '')),
                'productOrMilestone' => $this->resolveProductOrMilestone($ticket),
                'priority' => $priorityLabels[$ticket['priority'] ?? ''] ?? 'No Priority',
            ];
        }, $tickets);
    }

    private function formatDueDate(string $dateToFinish): string
    {
        $dateToFinish = trim($dateToFinish);

        if ($dateToFinish === '' || str_starts_with($dateToFinish, '0000-00-00')) {
            return 'No Due Date';
        }

        return substr($dateToFinish, 0, 10);
    }

    private function resolveProductOrMilestone(array $ticket): string
    {
        $milestone = trim((string) ($ticket['milestoneHeadline'] ?? ''));
        if ($milestone !== '') {
            return $milestone;
        }

        $product = trim((string) ($ticket['clientName'] ?? ''));
        if ($product !== '') {
            return $product;
        }

        $project = trim((string) ($ticket['projectName'] ?? ''));
        if ($project !== '') {
            return $project;
        }

        return 'No Product or Milestone';
    }

    /**
     * @param  array<int, array<string, mixed>>  $users
     * @return array<string, array<string, mixed>>
     */
    private function indexUsersById(array $users): array
    {
        $indexed = [];

        foreach ($users as $user) {
            if (! isset($user['id'])) {
                continue;
            }

            $indexed[(string) $user['id']] = $user;
        }

        return $indexed;
    }
}
