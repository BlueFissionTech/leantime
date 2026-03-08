<?php

namespace Leantime\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SyncFtpToS3Command extends Command
{
    protected $signature = 'migration:sync-ftp-to-s3
                            {--ftp-file=artifacts/ftp : Path to FTP credentials file (fallback if env/options missing)}
                            {--ftp-host= : FTP host (overrides env/file)}
                            {--ftp-user= : FTP username (overrides env/file)}
                            {--ftp-password= : FTP password (overrides env/file)}
                            {--ftp-port=21 : FTP port}
                            {--remote-path=userfiles : Remote base path to sync}
                            {--dry-run : Show what would be uploaded without writing to S3}
                            {--limit=0 : Process only the first N files (0 = all)}
                            {--db-only : Only upload files referenced in zp_file}
                            {--preserve-path : Preserve remote subfolders instead of basename-only keys}';

    protected $description = 'Sync legacy FTP files into configured S3 storage for production cutover';

    public function handle(FilesystemManager $filesystems): int
    {
        if (! extension_loaded('ftp')) {
            $this->error('PHP FTP extension is required for this command.');

            return Command::FAILURE;
        }

        $creds = $this->resolveFtpCredentials();
        $remotePath = trim((string) $this->option('remote-path'));
        $remotePath = $remotePath === '' ? 'userfiles' : trim($remotePath, '/');
        $dryRun = (bool) $this->option('dry-run');
        $preservePath = (bool) $this->option('preserve-path');
        $limit = (int) $this->option('limit');
        $limit = $limit > 0 ? $limit : 0;

        $this->info(sprintf('Connecting to FTP host "%s"...', $creds['host']));
        $conn = @ftp_connect($creds['host'], $creds['port'], 30);
        if ($conn === false) {
            $this->error('Could not connect to FTP server.');

            return Command::FAILURE;
        }

        try {
            if (! @ftp_login($conn, $creds['user'], $creds['password'])) {
                throw new RuntimeException('FTP login failed.');
            }
            ftp_pasv($conn, true);

            $files = $this->listFilesRecursive($conn, $remotePath);
            if (count($files) === 0) {
                $this->warn('No files found on FTP path: '.$remotePath);

                return Command::SUCCESS;
            }

            sort($files);

            if ($limit > 0) {
                $files = array_slice($files, 0, $limit);
            }

            $dbFileSet = [];
            if ((bool) $this->option('db-only')) {
                $dbFileSet = $this->loadDbFileNames();
                $this->info('Loaded '.count($dbFileSet).' DB-referenced files from zp_file.');
            }

            $s3 = $filesystems->disk('s3');
            $s3Prefix = trim((string) config('filesystems.disks.s3.root', ''), '/');

            $processed = 0;
            $skipped = 0;
            $uploaded = 0;
            $failed = 0;

            foreach ($files as $remoteFile) {
                $processed++;
                $baseName = basename($remoteFile);

                if (! empty($dbFileSet) && ! isset($dbFileSet[$baseName])) {
                    $skipped++;
                    continue;
                }

                $relative = ltrim(substr($remoteFile, strlen($remotePath)), '/');
                $targetLeaf = $preservePath ? $relative : $baseName;
                $targetKey = ltrim($s3Prefix.'/'.$targetLeaf, '/');

                if ($dryRun) {
                    $this->line(sprintf('[dry-run] %s -> s3://%s', $remoteFile, $targetKey));
                    $uploaded++;
                    continue;
                }

                $tempFile = tempnam(sys_get_temp_dir(), 'ltftp_');
                if ($tempFile === false) {
                    $this->error('Failed to create temp file for '.$remoteFile);
                    $failed++;
                    continue;
                }

                try {
                    if (! @ftp_get($conn, $tempFile, $remoteFile, FTP_BINARY)) {
                        throw new RuntimeException('FTP download failed.');
                    }

                    $stream = fopen($tempFile, 'rb');
                    if ($stream === false) {
                        throw new RuntimeException('Could not open temp file for upload.');
                    }

                    try {
                        if (! $s3->put($targetKey, $stream)) {
                            throw new RuntimeException('S3 upload failed.');
                        }
                    } finally {
                        fclose($stream);
                    }

                    $uploaded++;
                    $this->line(sprintf('[ok] %s -> s3://%s', $remoteFile, $targetKey));
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error(sprintf('[failed] %s (%s)', $remoteFile, $e->getMessage()));
                } finally {
                    @unlink($tempFile);
                }
            }

            $this->newLine();
            $this->info('FTP to S3 sync summary');
            $this->line('Processed: '.$processed);
            $this->line('Uploaded: '.$uploaded);
            $this->line('Skipped: '.$skipped);
            $this->line('Failed: '.$failed);

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        } finally {
            ftp_close($conn);
        }
    }

    /**
     * @return array{host:string,user:string,password:string,port:int}
     */
    private function resolveFtpCredentials(): array
    {
        $host = trim((string) $this->option('ftp-host'));
        $user = trim((string) $this->option('ftp-user'));
        $password = (string) ($this->option('ftp-password') ?? '');
        $port = (int) $this->option('ftp-port');
        $port = $port > 0 ? $port : 21;

        if ($host !== '' && $user !== '' && $password !== '') {
            return [
                'host' => $host,
                'user' => $user,
                'password' => $password,
                'port' => $port,
            ];
        }

        // Preferred env format for legacy FTP credentials.
        $envHost = $this->readEnvValue(['FTP_OLD_HOST', 'FTP_HOST']);
        $envUser = $this->readEnvValue(['FTP_OLD_USER', 'FTP_USER']);
        $envPassword = $this->readEnvValue(['FTP_OLD_PASS', 'FTP_OLD_PASSWORD', 'FTP_PASSWORD']);
        $envPort = $this->readEnvValue(['FTP_OLD_PORT', 'FTP_PORT']);

        if ($envHost !== '' && $envUser !== '' && $envPassword !== '') {
            return [
                'host' => $envHost,
                'user' => $envUser,
                'password' => $envPassword,
                'port' => $envPort !== '' ? max(1, (int) $envPort) : $port,
            ];
        }

        $ftpFile = (string) $this->option('ftp-file');
        if (! file_exists($ftpFile)) {
            throw new RuntimeException(
                'FTP credentials not found in options or env (FTP_OLD_HOST/FTP_OLD_USER/FTP_OLD_PASS), '.
                'and fallback file was not found: '.$ftpFile
            );
        }

        $lines = array_values(
            array_filter(
                array_map('trim', file($ftpFile) ?: []),
                static fn ($line) => $line !== ''
            )
        );

        // Supported formats:
        // 1) key=value lines (FTP_HOST, FTP_USER, FTP_PASSWORD, FTP_PORT optional)
        // 2) 4-line legacy format: "FTP:", host, user, password
        $kv = [];
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $kv[strtoupper(trim($k))] = trim(trim($v), " \t\n\r\0\x0B'\"");
            }
        }

        if (isset($kv['FTP_HOST'], $kv['FTP_USER'], $kv['FTP_PASSWORD'])) {
            return [
                'host' => $kv['FTP_HOST'],
                'user' => $kv['FTP_USER'],
                'password' => $kv['FTP_PASSWORD'],
                'port' => isset($kv['FTP_PORT']) ? max(1, (int) $kv['FTP_PORT']) : $port,
            ];
        }

        if (count($lines) >= 4 && str_ends_with(strtoupper($lines[0]), ':')) {
            return [
                'host' => $lines[1],
                'user' => $lines[2],
                'password' => $lines[3],
                'port' => $port,
            ];
        }

        throw new RuntimeException('Could not parse FTP credentials file. Use options or a supported file format.');
    }

    private function readEnvValue(array $keys): string
    {
        foreach ($keys as $key) {
            $value = $_ENV[$key] ?? getenv($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function listFilesRecursive($conn, string $path): array
    {
        $results = [];
        $stack = [$path];
        $seen = [];

        while (! empty($stack)) {
            $current = array_pop($stack);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;

            $entries = @ftp_nlist($conn, $current);
            if ($entries === false) {
                continue;
            }

            foreach ($entries as $entry) {
                $name = basename($entry);
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $size = @ftp_size($conn, $entry);
                if ($size === -1) {
                    $stack[] = $entry;
                    continue;
                }

                $results[] = $entry;
            }
        }

        return $results;
    }

    /**
     * @return array<string, true>
     */
    private function loadDbFileNames(): array
    {
        $rows = DB::table('zp_file')
            ->select('encName', 'extension')
            ->whereNotNull('encName')
            ->whereNotNull('extension')
            ->get();

        $set = [];
        foreach ($rows as $row) {
            $key = trim((string) $row->encName).'.'.trim((string) $row->extension);
            $set[$key] = true;
        }

        return $set;
    }
}
