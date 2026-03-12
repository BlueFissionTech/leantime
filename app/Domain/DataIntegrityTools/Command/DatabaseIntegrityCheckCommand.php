<?php

namespace Leantime\Domain\DataIntegrityTools\Command;

use Illuminate\Console\Command;
use Leantime\Domain\DataIntegrityTools\Services\IntegrityCheckService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'integrity:db-check',
    description: 'Run data integrity checks against target or source database',
)]
class DatabaseIntegrityCheckCommand extends Command
{
    protected $signature = 'integrity:db-check
                            {--source=target : target (LEAN_DB_*) or old/source (LEAN_OLD_DB_*)}
                            {--json : Output JSON report}
                            {--fail-on-errors : Return non-zero if missing tables or issues are found}';

    protected $description = 'Checks for missing base tables and common orphaned ticket relationships';

    public function __construct(private readonly IntegrityCheckService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $jsonOutput = (bool) $this->option('json');
        $failOnErrors = (bool) $this->option('fail-on-errors');

        try {
            $report = $this->service->run($source);
        } catch (\Throwable $e) {
            $this->error('Integrity check failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $issueCount = 0;
        foreach ($report['checks'] as $check) {
            if (($check['count'] ?? 0) > 0) {
                $issueCount += (int) $check['count'];
            }
        }

        $missingTableCount = count($report['missingTables']);

        if ($jsonOutput) {
            $this->line((string) json_encode([
                'summary' => [
                    'source' => $report['source'],
                    'host' => $report['target']['host'],
                    'port' => $report['target']['port'],
                    'database' => $report['target']['database'],
                    'missingTableCount' => $missingTableCount,
                    'issueCount' => $issueCount,
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
                        '- %s: %s (count=%d) - %s',
                        $check['name'],
                        $check['status'],
                        $check['count'],
                        $check['message']
                    ));
                }
            }

            $this->newLine();
            $this->line(sprintf('Summary: missing_tables=%d, integrity_issues=%d', $missingTableCount, $issueCount));
        }

        if ($failOnErrors && ($missingTableCount > 0 || $issueCount > 0)) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

