<?php

namespace Leantime\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'migration:import-sql',
    description: 'Import a MySQL dump file into target DB configured by LEAN_DB_*',
)]
class ImportSqlCommand extends Command
{
    protected $signature = 'migration:import-sql
                            {--file= : Path to SQL dump file}
                            {--s3-key= : S3 object key for SQL dump (e.g. bkup/u706165963_bfil_leantime.sql)}
                            {--dry-run : Parse statements only, do not execute}
                            {--continue-on-error : Continue processing statements after an error}';

    protected $description = 'Import SQL dump to target MySQL without mysql client binary';

    public function handle(FilesystemManager $filesystems): int
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->error('pdo_mysql extension is required.');

            return Command::FAILURE;
        }

        [$handle, $sourceLabel] = $this->resolveSqlStream($filesystems);

        $target = $this->resolveTargetConfig();
        $dryRun = (bool) $this->option('dry-run');
        $continueOnError = (bool) $this->option('continue-on-error');

        $this->info('Import source: '.$sourceLabel);
        $this->info(sprintf(
            'Target DB: %s:%s/%s',
            $target['host'],
            $target['port'],
            $target['database']
        ));
        if ($dryRun) {
            $this->warn('Running in dry-run mode.');
        }

        $pdo = $this->connect($target);

        $statementCount = 0;
        $successCount = 0;
        $errorCount = 0;

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach ($this->statementGenerator($handle) as $sql) {
                    $statementCount++;

                    if ($dryRun) {
                        if (($statementCount % 500) === 0) {
                            $this->line("Parsed statements: {$statementCount}");
                        }
                        continue;
                    }

                    try {
                        $pdo->exec($sql);
                        $successCount++;
                    } catch (\Throwable $e) {
                        $errorCount++;
                        $this->error(sprintf('Statement %d failed: %s', $statementCount, $e->getMessage()));
                        if (! $continueOnError) {
                            throw $e;
                        }
                    }

                    if (($statementCount % 250) === 0) {
                        $this->line(sprintf(
                            'Processed: %d (ok=%d, errors=%d)',
                            $statementCount,
                            $successCount,
                            $errorCount
                        ));
                    }
                }
            } finally {
                fclose($handle);
            }
        } catch (\Throwable $e) {
            $this->error('Import aborted: '.$e->getMessage());

            return Command::FAILURE;
        } finally {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
                // Ignore reset failure on shutdown.
            }
        }

        $this->newLine();
        $this->info('SQL import summary');
        $this->line('Statements parsed: '.$statementCount);
        if (! $dryRun) {
            $this->line('Statements executed: '.$successCount);
            $this->line('Statement errors: '.$errorCount);
        }

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
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

    private function assertNotEmpty(string $value, string $name): void
    {
        if ($value === '') {
            throw new RuntimeException("Missing required configuration: {$name}");
        }
    }

    private function guessDefaultSqlFile(): string
    {
        $candidates = [
            APP_ROOT.'/artifacts/u706165963_bfil_leantime.sql',
            APP_ROOT.'/artifacts/migration/prod.sql',
            APP_ROOT.'/artifacts/prod.sql',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $artifactSql = glob(APP_ROOT.'/artifacts/*.sql') ?: [];
        if (! empty($artifactSql)) {
            return $artifactSql[0];
        }

        return '';
    }

    /**
     * @return array{0:resource,1:string}
     */
    private function resolveSqlStream(FilesystemManager $filesystems): array
    {
        $file = trim((string) $this->option('file'));
        $s3Key = trim((string) $this->option('s3-key'));

        if ($file !== '') {
            if (! is_file($file)) {
                throw new RuntimeException('SQL dump file not found: '.$file);
            }
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                throw new RuntimeException('Could not open SQL file for reading: '.$file);
            }

            return [$handle, $file];
        }

        if ($s3Key === '') {
            $guessedFile = $this->guessDefaultSqlFile();
            if ($guessedFile !== '' && is_file($guessedFile)) {
                $handle = fopen($guessedFile, 'rb');
                if ($handle === false) {
                    throw new RuntimeException('Could not open SQL file for reading: '.$guessedFile);
                }

                return [$handle, $guessedFile];
            }

            $s3Key = $this->guessDefaultS3Key();
        }

        if ($s3Key === '') {
            throw new RuntimeException('No SQL input found. Pass --file or --s3-key.');
        }

        $s3Key = ltrim($s3Key, '/');
        $disk = $filesystems->disk('s3');
        if (! $disk->exists($s3Key)) {
            throw new RuntimeException('S3 SQL object not found: '.$s3Key);
        }

        $handle = $disk->readStream($s3Key);
        if (! is_resource($handle)) {
            throw new RuntimeException('Could not open S3 SQL object stream: '.$s3Key);
        }

        $bucket = (string) (getenv('LEAN_S3_BUCKET') ?: '');
        $label = $bucket !== '' ? "s3://{$bucket}/{$s3Key}" : "s3://{$s3Key}";

        return [$handle, $label];
    }

    private function guessDefaultS3Key(): string
    {
        $oldDb = trim((string) (getenv('LEAN_OLD_DB_DATABASE') ?: ''));
        if ($oldDb !== '') {
            return 'bkup/'.$oldDb.'.sql';
        }

        return '';
    }

    /**
     * @param  resource  $handle
     * @return \Generator<int, string>
     */
    private function statementGenerator($handle): \Generator
    {
        $delimiter = ';';
        $buffer = '';
        $inSingle = false;
        $inDouble = false;

        while (($line = fgets($handle)) !== false) {
            $trimmed = ltrim($line);

            if ($buffer === '' && ($trimmed === '' || $trimmed === "\n" || $trimmed === "\r\n")) {
                continue;
            }
            if ($buffer === '' && (str_starts_with($trimmed, '-- ') || str_starts_with($trimmed, '#'))) {
                continue;
            }
            if ($buffer === '' && preg_match('/^\/\*![0-9]+\s+/', $trimmed) === 1) {
                continue;
            }
            if (preg_match('/^\s*DELIMITER\s+(.+)\s*$/i', $line, $m) === 1) {
                $delimiter = trim($m[1]);
                continue;
            }

            $buffer .= $line;

            $length = strlen($buffer);
            $escaped = false;
            for ($i = 0; $i < $length; $i++) {
                $char = $buffer[$i];
                if ($escaped) {
                    $escaped = false;
                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }
                if (! $inDouble && $char === "'") {
                    $inSingle = ! $inSingle;
                    continue;
                }
                if (! $inSingle && $char === '"') {
                    $inDouble = ! $inDouble;
                    continue;
                }
            }

            if (! $inSingle && ! $inDouble && $this->endsWithDelimiter($buffer, $delimiter)) {
                $statement = trim(substr($buffer, 0, strlen($buffer) - strlen($delimiter)));
                $buffer = '';

                if ($statement !== '') {
                    yield $statement;
                }
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            yield $tail;
        }
    }

    private function endsWithDelimiter(string $buffer, string $delimiter): bool
    {
        $trimmed = rtrim($buffer);
        if ($delimiter === '') {
            return false;
        }

        return str_ends_with($trimmed, $delimiter);
    }
}

