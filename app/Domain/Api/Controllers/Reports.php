<?php

namespace Leantime\Domain\Api\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Comments\Services\Comments as CommentService;
use Leantime\Domain\Reports\Repositories\Reports as ReportRepository;
use Leantime\Domain\Reports\Services\Reports as ReportService;
use Symfony\Component\HttpFoundation\Response;

class Reports extends Controller
{
    private ReportService $reportService;

    private ReportRepository $reportRepository;

    private CommentService $commentService;

    public function init(
        ReportService $reportService,
        ReportRepository $reportRepository,
        CommentService $commentService
    ): void {
        $this->reportService = $reportService;
        $this->reportRepository = $reportRepository;
        $this->commentService = $commentService;
    }

    public function get(array $params): Response
    {
        if (! isset($params['projectId'])) {
            return $this->tpl->displayJson(['status' => 'failure', 'error' => 'projectId is required'], 400);
        }

        $projectId = (int) $params['projectId'];
        $report = $params['report'] ?? 'full';

        return match ($report) {
            'full' => $this->tpl->displayJson(['status' => 'ok', 'result' => $this->reportService->getFullReport($projectId)]),
            'realtime' => $this->tpl->displayJson(['status' => 'ok', 'result' => $this->reportService->getRealtimeReport($projectId, (string) ($params['sprintId'] ?? ''))]),
            'backlog' => $this->tpl->displayJson(['status' => 'ok', 'result' => $this->reportRepository->getBacklogReport($projectId)]),
            'sprint' => isset($params['sprintId'])
                ? $this->tpl->displayJson(['status' => 'ok', 'result' => $this->reportRepository->getSprintReport((int) $params['sprintId'])])
                : $this->tpl->displayJson(['status' => 'failure', 'error' => 'sprintId is required'], 400),
            'projectStatusSummary' => $this->tpl->displayJson(['status' => 'ok', 'result' => $this->reportService->getProjectStatusReport()]),
            'statusHistory' => $this->tpl->displayJson([
                'status' => 'ok',
                'result' => [
                    'qualitative' => $this->commentService->getComments('project', $projectId, 1),
                    'quantitative' => $this->reportService->getFullReport($projectId),
                    'realtime' => $this->reportService->getRealtimeReport($projectId, (string) ($params['sprintId'] ?? '')),
                ],
            ]),
            default => $this->tpl->displayJson(['status' => 'failure', 'error' => 'Unsupported report type'], 400),
        };
    }

    public function post(array $params): Response
    {
        return $this->tpl->displayJson(['status' => 'Not implemented'], 501);
    }

    public function patch(array $params): Response
    {
        return $this->tpl->displayJson(['status' => 'Not implemented'], 501);
    }

    public function delete(array $params): Response
    {
        return $this->tpl->displayJson(['status' => 'Not implemented'], 501);
    }
}
