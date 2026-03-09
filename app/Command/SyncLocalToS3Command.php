<?php

namespace Leantime\Command;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'migration:sync-local-to-s3',
    description: 'Sync local files directory to configured S3 for cutover',
)]
class SyncLocalToS3Command extends Command
{
    protected $signature = 'migration:sync-local-to-s3
                            {--source=artifacts/userfiles : Local source directory}
                            {--dry-run : Show what would be uploaded without writing}
                            {--db-only : Upload only files referenced in zp_file}
                            {--preserve-path : Preserve source subfolders; default uploads basename only}';

    protected $description = 'Upload local files to S3 using app credentials (LEAN_S3_*)';

    public function handle(FilesystemManager $filesystems): int
    {
        $source = trim((string) $this->option('source'));
        $source = $source === '' ? 'artifacts/userfiles' : $source;
        $sourcePath = realpath($source);

        if ($sourcePath === false || ! is_dir($sourcePath)) {
            $this->error('Source directory not found: '.$source);

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $dbOnly = (bool) $this->option('db-only');
        $preservePath = (bool) $this->option('preserve-path');

        $disk = $filesystems->disk('s3');
        $prefix = trim((string) config('filesystems.disks.s3.root', ''), '/');

        $dbSet = [];
        if ($dbOnly) {
            $dbSet = $this->loadDbFileNames();
            $this->info('Loaded '.count($dbSet).' DB-referenced file names from zp_file.');
        }

        $files = $this->collectFiles($sourcePath);
        if (count($files) === 0) {
            $this->warn('No files found in '.$sourcePath);

            return Command::SUCCESS;
        }

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($files as $absolutePath) {
            $baseName = basename($absolutePath);

            if (! empty($dbSet) && ! isset($dbSet[$baseName])) {
                $skipped++;
                continue;
            }

            $relative = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($sourcePath))), '/');
            $targetLeaf = $preservePath ? $relative : $baseName;
            $targetKey = ltrim($prefix.'/'.$targetLeaf, '/');

            if ($dryRun) {
                $this->line(sprintf('[dry-run] %s -> s3://%s', $absolutePath, $targetKey));
                $uploaded++;
                continue;
            }

            $stream = fopen($absolutePath, 'rb');
            if ($stream === false) {
                $failed++;
                $this->error('[failed] Could not open file: '.$absolutePath);
                continue;
            }

            try {
                if (! $disk->put($targetKey, $stream)) {
                    throw new RuntimeException('S3 upload failed.');
                }
                $uploaded++;
                $this->line(sprintf('[ok] %s -> s3://%s', $absolutePath, $targetKey));
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf('[failed] %s (%s)', $absolutePath, $e->getMessage()));
            } finally {
                fclose($stream);
            }
        }

        $this->newLine();
        $this->info('Local to S3 sync summary');
        $this->line('Source files: '.count($files));
        $this->line('Uploaded: '.$uploaded);
        $this->line('Skipped: '.$skipped);
        $this->line('Failed: '.$failed);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function collectFiles(string $sourcePath): array
    {
        $results = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $results[] = $item->getPathname();
        }

        sort($results);

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
            $name = trim((string) $row->encName).'.'.trim((string) $row->extension);
            $set[$name] = true;
        }

        return $set;
    }
}

