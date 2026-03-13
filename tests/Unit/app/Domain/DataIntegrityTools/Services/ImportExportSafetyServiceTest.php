<?php

namespace Unit\app\Domain\DataIntegrityTools\Services;

use Leantime\Domain\DataIntegrityTools\Services\ImportExportSafetyService;
use Unit\TestCase;

class ImportExportSafetyServiceTest extends TestCase
{
    private ImportExportSafetyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ImportExportSafetyService;
    }

    public function test_analyze_statements_counts_statement_types(): void
    {
        $report = $this->service->analyzeStatements([
            'CREATE TABLE `zp_projects` (`id` int);',
            'INSERT INTO `zp_projects` (`id`) VALUES (1);',
            'ALTER TABLE `zp_projects` ADD COLUMN `name` varchar(255);',
            'UPDATE `zp_projects` SET `name` = \'Test\' WHERE `id` = 1;',
        ]);

        $this->assertSame(4, $report['statements']);
        $this->assertSame(1, $report['createTables']);
        $this->assertSame(1, $report['inserts']);
        $this->assertSame(1, $report['alters']);
        $this->assertSame(1, $report['updates']);
        $this->assertSame(0, $report['destructiveCount']);
    }

    public function test_analyze_statements_flags_destructive_sql(): void
    {
        $report = $this->service->analyzeStatements([
            'DROP TABLE IF EXISTS `zp_projects`;',
            'TRUNCATE TABLE `zp_tickets`;',
        ]);

        $this->assertSame(2, $report['destructiveCount']);
        $this->assertCount(2, $report['destructiveStatements']);
        $this->assertStringContainsString('DROP TABLE', $report['destructiveStatements'][0]);
        $this->assertStringContainsString('TRUNCATE TABLE', $report['destructiveStatements'][1]);
    }
}
