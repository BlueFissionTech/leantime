<?php

namespace Leantime\Domain\Taskcsvexport\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Domain\Auth\Models\Roles;
use Leantime\Domain\Auth\Services\Auth;
use Leantime\Domain\Taskcsvexport\Services\TaskCsvExport as TaskCsvExportService;
use Symfony\Component\HttpFoundation\Response;

class Export extends Controller
{
    private TaskCsvExportService $taskCsvExportService;

    public function init(TaskCsvExportService $taskCsvExportService): void
    {
        Auth::authOrRedirect([Roles::$owner, Roles::$admin, Roles::$manager], true);

        $this->taskCsvExportService = $taskCsvExportService;
    }

    public function get(): Response
    {
        $csv = $this->taskCsvExportService->buildCsv($_GET);

        return new Response(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="team-task-export-'.date('Ymd-His').'.csv"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        );
    }
}
