<?php

namespace Leantime\Command;

use Illuminate\Console\Command;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'migration:copy-db',
    description: 'Copy Leantime data directly from legacy MySQL (LEAN_OLD_DB_*) into current target DB (LEAN_DB_*)',
)]
class CopyDbCommand extends Command
{
    protected $signature = 'migration:copy-db
                            {--source-host= : Legacy DB host (default: LEAN_OLD_DB_HOST)}
                            {--source-port= : Legacy DB port (default: LEAN_OLD_DB_PORT or 3306)}
                            {--source-user= : Legacy DB user (default: LEAN_OLD_DB_USER)}
                            {--source-password= : Legacy DB password (default: LEAN_OLD_DB_PASSWORD)}
                            {--source-database= : Legacy DB name (default: LEAN_OLD_DB_DATABASE)}
                            {--table-prefix=zp_ : Copy only tables with this prefix}
                            {--tables= : Comma-separated explicit table list to copy}
                            {--skip-truncate : Do not truncate target tables before insert}
                            {--dry-run : Print planned operations only}';

    protected $description = 'Direct DB-to-DB copy for environments without mysqldump/mysql binaries';

    public function handle(): int
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->error('pdo_mysql extension is required.');

            return Command::FAILURE;
        }

        $source = $this->resolveSourceConfig();
        $target = $this->resolveTargetConfig();
        $dryRun = (bool) $this->option('dry-run');
        $skipTruncate = (bool) $this->option('skip-truncate');
        $tablePrefix = (string) $this->option('table-prefix');

        $this->info(sprintf(
            'Source: %s:%s/%s  ->  Target: %s:%s/%s',
            $source['host'],
            $source['port'],
            $source['database'],
            $target['host'],
            $target['port'],
            $target['database']
        ));
        if ($dryRun) {
            $this->warn('Running in dry-run mode.');
        }

        try {
            $sourcePdo = $this->connect($source, true);
            $targetPdo = $this->connect($target, false);

            $tables = $this->resolveTables($sourcePdo, $tablePrefix);
            if (count($tables) === 0) {
                $this->warn('No tables matched for copy.');

                return Command::SUCCESS;
            }

            $this->line('Tables to process: '.count($tables));
            if (! $dryRun) {
                $targetPdo->exec('SET FOREIGN_KEY_CHECKS=0');
            }

            $copiedRows = 0;
            $skippedTables = 0;
            $failedTables = 0;

            foreach ($tables as $table) {
                $this->newLine();
                $this->line('Processing table: '.$table);

                try {
                    $sourceColumns = $this->columnsFor($sourcePdo, $table);
                    $targetColumns = $this->columnsFor($targetPdo, $table);

                    if (count($targetColumns) === 0) {
                        $this->warn('  skipped (target table missing)');
                        $skippedTables++;
                        continue;
                    }

                    $commonColumns = array_values(array_intersect($sourceColumns, $targetColumns));
                    if (count($commonColumns) === 0) {
                        $this->warn('  skipped (no common columns)');
                        $skippedTables++;
                        continue;
                    }

                    $sourceCount = (int) $sourcePdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                    $this->line('  source rows: '.$sourceCount);
                    $this->line('  common columns: '.count($commonColumns));

                    if ($dryRun) {
                        continue;
                    }

                    if (! $skipTruncate) {
                        $targetPdo->exec("TRUNCATE TABLE `{$table}`");
                    }

                    $quotedColumns = implode(
                        ',',
                        array_map(static fn ($column) => "`{$column}`", $commonColumns)
                    );
                    $placeholders = implode(',', array_fill(0, count($commonColumns), '?'));

                    $selectSql = "SELECT {$quotedColumns} FROM `{$table}`";
                    $insertSql = "INSERT INTO `{$table}` ({$quotedColumns}) VALUES ({$placeholders})";

                    $selectStmt = $sourcePdo->query($selectSql);
                    $insertStmt = $targetPdo->prepare($insertSql);

                    $targetPdo->beginTransaction();
                    $tableRows = 0;

                    while (($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                        $values = [];
                        foreach ($commonColumns as $column) {
                            $values[] = $row[$column] ?? null;
                        }
                        $insertStmt->execute($values);
                        $tableRows++;
                        $copiedRows++;

                        if (($tableRows % 1000) === 0) {
                            $this->line('  copied rows: '.$tableRows);
                        }
                    }

                    $targetPdo->commit();
                    $this->info('  copied rows total: '.$tableRows);
                } catch (\Throwable $e) {
                    $failedTables++;
                    if ($targetPdo->inTransaction()) {
                        $targetPdo->rollBack();
                    }
                    $this->error('  failed: '.$e->getMessage());
                }
            }

            if (! $dryRun) {
                $targetPdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }

            $this->newLine();
            $this->info('Copy summary');
            $this->line('Tables processed: '.count($tables));
            $this->line('Tables skipped: '.$skippedTables);
            $this->line('Tables failed: '.$failedTables);
            $this->line('Rows copied: '.$copiedRows);

            return $failedTables > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array{host:string,port:string,user:string,password:string,database:string}
     */
    private function resolveSourceConfig(): array
    {
        $host = $this->resolveOptionOrEnv('source-host', 'LEAN_OLD_DB_HOST');
        $port = $this->resolveOptionOrEnv('source-port', 'LEAN_OLD_DB_PORT', '3306');
        $user = $this->resolveOptionOrEnv('source-user', 'LEAN_OLD_DB_USER');
        $password = $this->resolveOptionOrEnv('source-password', 'LEAN_OLD_DB_PASSWORD');
        $database = $this->resolveOptionOrEnv('source-database', 'LEAN_OLD_DB_DATABASE');

        $this->assertNotEmpty($host, 'LEAN_OLD_DB_HOST');
        $this->assertNotEmpty($user, 'LEAN_OLD_DB_USER');
        $this->assertNotEmpty($password, 'LEAN_OLD_DB_PASSWORD');
        $this->assertNotEmpty($database, 'LEAN_OLD_DB_DATABASE');

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'database' => $database,
        ];
    }

    /**
     * @return array{host:string,port:string,user:string,password:string,database:string}
     */
    private function resolveTargetConfig(): array
    {
        $host = trim((string) (getenv('LEAN_DB_HOST') ?: ''));
        $port = trim((string) (getenv('LEAN_DB_PORT') ?: '3306'));
        $user = trim((string) (getenv('LEAN_DB_USER') ?: ''));
        $password = (string) (getenv('LEAN_DB_PASSWORD') ?: '');
        $database = trim((string) (getenv('LEAN_DB_DATABASE') ?: ''));

        $this->assertNotEmpty($host, 'LEAN_DB_HOST');
        $this->assertNotEmpty($user, 'LEAN_DB_USER');
        $this->assertNotEmpty($password, 'LEAN_DB_PASSWORD');
        $this->assertNotEmpty($database, 'LEAN_DB_DATABASE');

        return [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'password' => $password,
            'database' => $database,
        ];
    }

    /**
     * @param  array{host:string,port:string,user:string,password:string,database:string}  $config
     */
    private function connect(array $config, bool $unbuffered): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => ! $unbuffered,
        ];

        return new PDO($dsn, $config['user'], $config['password'], $options);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTables(PDO $sourcePdo, string $tablePrefix): array
    {
        $explicit = trim((string) $this->option('tables'));
        if ($explicit !== '') {
            $tables = array_values(array_filter(array_map('trim', explode(',', $explicit))));

            return array_values(array_unique($tables));
        }

        $rows = $sourcePdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        $tables = [];
        foreach ($rows as $row) {
            $table = (string) ($row[0] ?? '');
            if ($table === '') {
                continue;
            }
            if ($tablePrefix !== '' && ! str_starts_with($table, $tablePrefix)) {
                continue;
            }
            $tables[] = $table;
        }

        return $tables;
    }

    /**
     * @return array<int, string>
     */
    private function columnsFor(PDO $pdo, string $table): array
    {
        try {
            $rows = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            if (isset($row['Field'])) {
                $columns[] = (string) $row['Field'];
            }
        }

        return $columns;
    }

    private function resolveOptionOrEnv(string $option, string $envKey, string $default = ''): string
    {
        $optionValue = trim((string) ($this->option($option) ?? ''));
        if ($optionValue !== '') {
            return $optionValue;
        }

        $envValue = trim((string) (getenv($envKey) ?: ''));
        if ($envValue !== '') {
            return $envValue;
        }

        return $default;
    }

    private function assertNotEmpty(string $value, string $name): void
    {
        if ($value === '') {
            throw new RuntimeException("Missing required configuration: {$name}");
        }
    }
}

