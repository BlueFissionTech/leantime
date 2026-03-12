<?php

namespace Leantime\Domain\DataIntegrityTools\Services;

use PDO;
use RuntimeException;

class IntegrityCheckService
{
    /**
     * @return array{
     *   source:string,
     *   target:array{host:string,port:string,database:string},
     *   requiredTables:array<string>,
     *   missingTables:array<string>,
     *   checks:array<int, array{name:string,status:string,count:int,message:string}>
     * }
     */
    public function run(string $source = 'target'): array
    {
        $config = $this->resolveConfig($source);
        $pdo = $this->connect($config);

        $requiredTables = [
            'zp_projects',
            'zp_tickets',
            'zp_user',
        ];

        $missingTables = $this->findMissingTables($pdo, $config['database'], $requiredTables);
        $checks = [];

        if (! in_array('zp_tickets', $missingTables, true) && ! in_array('zp_projects', $missingTables, true)) {
            if ($this->tableHasColumns($pdo, $config['database'], 'zp_tickets', ['projectId'])
                && $this->tableHasColumns($pdo, $config['database'], 'zp_projects', ['id'])) {
                $count = $this->countRows($pdo, "SELECT COUNT(*) FROM zp_tickets t
                        LEFT JOIN zp_projects p ON CAST(t.projectId AS CHAR(64)) = CAST(p.id AS CHAR(64))
                        WHERE t.projectId IS NOT NULL AND t.projectId <> '' AND p.id IS NULL");
                $checks[] = $this->buildCheck(
                    'orphan_tickets_missing_project',
                    $count,
                    'Tickets referencing missing projects'
                );
            }
        }

        if (! in_array('zp_tickets', $missingTables, true)) {
            if ($this->tableHasColumns($pdo, $config['database'], 'zp_tickets', ['milestoneid', 'id'])) {
                $count = $this->countRows($pdo, "SELECT COUNT(*) FROM zp_tickets t
                        LEFT JOIN zp_tickets m ON CAST(t.milestoneid AS CHAR(64)) = CAST(m.id AS CHAR(64))
                        WHERE t.milestoneid IS NOT NULL
                          AND t.milestoneid <> ''
                          AND t.milestoneid <> '0'
                          AND m.id IS NULL");
                $checks[] = $this->buildCheck(
                    'orphan_tickets_missing_milestone',
                    $count,
                    'Tickets referencing missing milestones'
                );
            }
        }

        if (! in_array('zp_tickets', $missingTables, true) && ! in_array('zp_user', $missingTables, true)) {
            if ($this->tableHasColumns($pdo, $config['database'], 'zp_tickets', ['editorId'])
                && $this->tableHasColumns($pdo, $config['database'], 'zp_user', ['id'])) {
                $count = $this->countRows($pdo, "SELECT COUNT(*) FROM zp_tickets t
                        LEFT JOIN zp_user u ON CAST(t.editorId AS CHAR(64)) = CAST(u.id AS CHAR(64))
                        WHERE t.editorId IS NOT NULL AND t.editorId <> '' AND u.id IS NULL");
                $checks[] = $this->buildCheck(
                    'orphan_tickets_missing_assignee',
                    $count,
                    'Tickets referencing missing assignees'
                );
            }
        }

        return [
            'source' => $source,
            'target' => [
                'host' => $config['host'],
                'port' => $config['port'],
                'database' => $config['database'],
            ],
            'requiredTables' => $requiredTables,
            'missingTables' => $missingTables,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        return new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    }

    /**
     * @return array{host:string,port:string,user:string,password:string,database:string}
     */
    private function resolveConfig(string $source): array
    {
        $source = strtolower(trim($source));
        $prefix = match ($source) {
            'old', 'source' => 'LEAN_OLD_DB_',
            default => 'LEAN_DB_',
        };

        $host = trim((string) (getenv($prefix.'HOST') ?: ''));
        $port = trim((string) (getenv($prefix.'PORT') ?: '3306'));
        $user = trim((string) (getenv($prefix.'USER') ?: ''));
        $password = (string) (getenv($prefix.'PASSWORD') ?: '');
        $database = trim((string) (getenv($prefix.'DATABASE') ?: ''));

        $this->assertNotEmpty($host, $prefix.'HOST');
        $this->assertNotEmpty($user, $prefix.'USER');
        $this->assertNotEmpty($password, $prefix.'PASSWORD');
        $this->assertNotEmpty($database, $prefix.'DATABASE');

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'database' => $database,
        ];
    }

    private function assertNotEmpty(string $value, string $name): void
    {
        if ($value === '') {
            throw new RuntimeException("Missing required configuration: {$name}");
        }
    }

    /**
     * @param  array<string>  $requiredTables
     * @return array<string>
     */
    private function findMissingTables(PDO $pdo, string $database, array $requiredTables): array
    {
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $sql = "SELECT table_name FROM information_schema.tables
                WHERE table_schema = ? AND table_name IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$database], $requiredTables));
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_diff($requiredTables, $found));
    }

    /**
     * @param  array<string>  $columns
     */
    private function tableHasColumns(PDO $pdo, string $database, string $table, array $columns): bool
    {
        if (count($columns) === 0) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "SELECT column_name FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$database, $table], $columns));
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return count(array_diff($columns, $found)) === 0;
    }

    private function countRows(PDO $pdo, string $sql): int
    {
        $stmt = $pdo->query($sql);
        if ($stmt === false) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array{name:string,status:string,count:int,message:string}
     */
    private function buildCheck(string $name, int $count, string $message): array
    {
        return [
            'name' => $name,
            'status' => $count > 0 ? 'warning' : 'ok',
            'count' => $count,
            'message' => $message,
        ];
    }
}

