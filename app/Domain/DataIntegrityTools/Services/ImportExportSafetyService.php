<?php

namespace Leantime\Domain\DataIntegrityTools\Services;

use PDO;

class ImportExportSafetyService
{
    /**
     * @param  iterable<int, string>  $statements
     * @return array{
     *     statements:int,
     *     createTables:int,
     *     inserts:int,
     *     alters:int,
     *     updates:int,
     *     destructiveCount:int,
     *     destructiveStatements:array<int, string>
     * }
     */
    public function analyzeStatements(iterable $statements): array
    {
        $report = [
            'statements' => 0,
            'createTables' => 0,
            'inserts' => 0,
            'alters' => 0,
            'updates' => 0,
            'destructiveCount' => 0,
            'destructiveStatements' => [],
        ];

        foreach ($statements as $statement) {
            $normalized = strtoupper(ltrim($statement));
            $report['statements']++;

            if (str_starts_with($normalized, 'CREATE TABLE')) {
                $report['createTables']++;
            } elseif (str_starts_with($normalized, 'INSERT INTO')) {
                $report['inserts']++;
            } elseif (str_starts_with($normalized, 'ALTER TABLE')) {
                $report['alters']++;
            } elseif (str_starts_with($normalized, 'UPDATE ')) {
                $report['updates']++;
            }

            if ($this->isDestructive($normalized)) {
                $report['destructiveCount']++;
                if (count($report['destructiveStatements']) < 25) {
                    $report['destructiveStatements'][] = $this->summarizeStatement($statement);
                }
            }
        }

        return $report;
    }

    /**
     * @return array{
     *     requiredTables:array<string, bool>,
     *     rowCounts:array<string, int>,
     *     orphanChecks:array<string, int>
     * }
     */
    public function verifyDatabase(PDO $pdo): array
    {
        $requiredTables = [
            'zp_projects',
            'zp_tickets',
            'zp_user',
        ];

        $present = [];
        foreach ($requiredTables as $table) {
            $present[$table] = $this->tableExists($pdo, $table);
        }

        $rowCounts = [];
        foreach (array_keys(array_filter($present)) as $table) {
            $rowCounts[$table] = $this->countRows($pdo, sprintf('SELECT COUNT(*) FROM `%s`', $table));
        }

        $orphanChecks = [];
        if (($present['zp_tickets'] ?? false) && ($present['zp_projects'] ?? false)) {
            $orphanChecks['tickets_without_project'] = $this->countRows(
                $pdo,
                'SELECT COUNT(*) FROM `zp_tickets` t LEFT JOIN `zp_projects` p ON p.id = t.projectId WHERE t.projectId IS NOT NULL AND p.id IS NULL'
            );
            $orphanChecks['milestones_without_project'] = $this->countRows(
                $pdo,
                "SELECT COUNT(*) FROM `zp_tickets` t LEFT JOIN `zp_projects` p ON p.id = t.projectId WHERE t.type = 'milestone' AND t.projectId IS NOT NULL AND p.id IS NULL"
            );
        }

        if (($present['zp_timesheets'] ?? $this->tableExists($pdo, 'zp_timesheets')) && ($present['zp_tickets'] ?? false)) {
            $orphanChecks['timesheets_without_ticket'] = $this->countRows(
                $pdo,
                'SELECT COUNT(*) FROM `zp_timesheets` ts LEFT JOIN `zp_tickets` t ON t.id = ts.ticketId WHERE ts.ticketId IS NOT NULL AND t.id IS NULL'
            );
        }

        return [
            'requiredTables' => $present,
            'rowCounts' => $rowCounts,
            'orphanChecks' => $orphanChecks,
        ];
    }

    public function isDestructive(string $normalizedSql): bool
    {
        return preg_match('/^(DROP\s+TABLE|DROP\s+DATABASE|TRUNCATE\s+TABLE|TRUNCATE\s+)/i', $normalizedSql) === 1;
    }

    private function summarizeStatement(string $statement): string
    {
        $singleLine = preg_replace('/\s+/', ' ', trim($statement)) ?? trim($statement);

        return mb_substr($singleLine, 0, 180);
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $statement = $pdo->prepare('SHOW TABLES LIKE :table');
        $statement->execute(['table' => $table]);

        return (bool) $statement->fetchColumn();
    }

    private function countRows(PDO $pdo, string $sql): int
    {
        $statement = $pdo->query($sql);

        return (int) $statement->fetchColumn();
    }
}
