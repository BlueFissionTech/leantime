<?php

namespace Leantime\Command;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Configuration\Environment;
use Leantime\Domain\DataIntegrityTools\Services\PdoDatabaseDumpService;
use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class BackupDbCommand
 *
 * Command to back up the database.
 *
 * Usage:
 *   php bin/console db:backup
 */
#[AsCommand(
    name: 'db:backup',
    description: 'Backs up database',
)]
class BackupDbCommand extends Command
{
    protected function configure(): void
    {
        parent::configure();
    }

    /**
     * Execute the command
     *
     *
     * @return int 0 if everything went fine, or an exit code.
     *
     * @throws BindingResolutionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $config = app()->make(Environment::class);

        $io = new SymfonyStyle($input, $output);

        $date = new \DateTime;
        $backupFile = $config->dbDatabase.'_'.$date->format('Y-m-d').'.sql';
        $backupPath = APP_ROOT.'/'.$config->dbBackupPath.$backupFile;

        if (! is_dir(APP_ROOT.'/'.$config->dbBackupPath)) {
            mkdir(APP_ROOT.'/'.$config->dbBackupPath, 0755, true);
        }

        $commandOutput = [];
        $worked = 1;
        if ($this->hasMysqldumpBinary()) {
            $cmd = sprintf(
                'mysqldump --column-statistics=0 --user=\'%s\' --password=\'%s\' --host=%s %s --port=%s --result-file=%s 2>&1',
                $config->dbUser,
                $config->dbPassword,
                $config->dbHost == 'localhost' ? '127.0.0.1' : $config->dbHost,
                $config->dbDatabase,
                $config->dbPort,
                $backupPath
            );
            exec($cmd, $commandOutput, $worked);
        }

        if ($worked === 0) {
            chmod(APP_ROOT.'/'.$config->userFilePath, 0755);
            $io->success('Success, database was backed up successfully');

            return Command::SUCCESS;
        }

        if (! empty($commandOutput)) {
            $io->warning('mysqldump failed, falling back to PDO export.');
            $io->listing($commandOutput);
        } else {
            $io->warning('mysqldump not available, falling back to PDO export.');
        }

        try {
            $pdo = new PDO(
                sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $config->dbHost,
                    $config->dbPort,
                    $config->dbDatabase
                ),
                $config->dbUser,
                $config->dbPassword,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            app()->make(PdoDatabaseDumpService::class)->dump($pdo, $backupPath);
            chmod(APP_ROOT.'/'.$config->userFilePath, 0755);
            $io->success('Success, database was backed up successfully using PDO fallback');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('There was an issue backing up the database');
            $io->listing([$e->getMessage()]);

            return Command::FAILURE;
        }
    }

    private function hasMysqldumpBinary(): bool
    {
        $output = [];
        $worked = 1;
        exec('mysqldump --version 2>&1', $output, $worked);

        return $worked === 0;
    }
}
