<?php

namespace Leantime\Domain\DataIntegrityTools\Command;

use Illuminate\Console\Command;
use Leantime\Domain\DataIntegrityTools\Services\IntegrityCheckService;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Report orphan diagnostics and distinguish missing coverage from clean results.
 */
#[AsCommand(
    name: 'integrity:db-check',
    description: 'Run data integrity checks against target or source database',
)]
class DatabaseIntegrityCheckCommand extends Command
{
    protected $signature = 'integrity:db-check
                            {--source=target : target (LEAN_DB_*) or old/source (LEAN_OLD_DB_*)}
                            {--json : Output JSON report}
                            {--fail-on-errors : Return non-zero if tables are missing, issues are found, or check coverage is incomplete}';

    protected $description = 'Checks for missing base tables and common orphaned ticket relationships';

    /**
     * Bind the read-only diagnostic service.
     */
    public function __construct(private readonly IntegrityCheckService $service)
    {
        parent::__construct();
    }

    /**
     * Render the report and enforce optional strict coverage.
     *
     * @return int
     */
    public function handle(): int
    {
        $source = (string) $this->option('source');
        $jsonOutput = (bool) $this->option('json');
        $failOnErrors = (bool) $this->option('fail-on-errors');

        try {
            $report = $this->service->run($source);
        } catch (\Throwable $e) {
            $this->error('Integrity check failed: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $issueCount = 0;
        $completedChecks = [];
        foreach ($report['checks'] as $check) {
            if (
                in_array($check['status'] ?? '', ['ok', 'warning'], true)
                && is_int($check['count'] ?? null) && $check['count'] >= 0
            ) {
                $completedChecks[] = $check['name'];
            }
            if (($check['count'] ?? 0) > 0) {
                $issueCount += (int) $check['count'];
            }
        }

        $missingTableCount = count($report['missingTables']);
        $incompleteChecks = array_values(array_diff(IntegrityCheckService::EXPECTED_CHECKS, $completedChecks));
        $coverageComplete = count($incompleteChecks) === 0;

        if ($jsonOutput) {
            $this->line((string) json_encode([
                'summary' => [
                    'source' => $report['source'],
                    'host' => $report['target']['host'],
                    'port' => $report['target']['port'],
                    'database' => $report['target']['database'],
                    'missingTableCount' => $missingTableCount,
                    'issueCount' => $issueCount,
                    'coverageComplete' => $coverageComplete,
                    'incompleteChecks' => $incompleteChecks,
                ],
                'report' => $report,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info(sprintf(
                'Checking %s database: %s:%s/%s',
                $report['source'],
                $report['target']['host'],
                $report['target']['port'],
                $report['target']['database']
            ));

            $this->newLine();
            $this->line('Required tables');
            foreach ($report['requiredTables'] as $table) {
                $state = in_array($table, $report['missingTables'], true) ? 'missing' : 'ok';
                $this->line(sprintf('- %s: %s', $table, $state));
            }

            $this->newLine();
            $this->line('Integrity checks');
            if (count($report['checks']) === 0) {
                $this->line('- no checks executed (likely missing prerequisite tables/columns)');
            } else {
                foreach ($report['checks'] as $check) {
                    $this->line(sprintf(
                        '- %s: %s (count=%s) - %s',
                        $check['name'],
                        $check['status'],
                        $check['count'] ?? 'unknown',
                        $check['message']
                    ));
                }
            }

            $this->newLine();
            $this->line(sprintf('Summary: missing_tables=%d, integrity_issues=%d', $missingTableCount, $issueCount));
            $this->line('Coverage: ' . ($coverageComplete ? 'complete' : 'incomplete (' . implode(', ', $incompleteChecks) . ')'));
        }

        if ($failOnErrors && ($missingTableCount > 0 || $issueCount > 0 || ! $coverageComplete)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
