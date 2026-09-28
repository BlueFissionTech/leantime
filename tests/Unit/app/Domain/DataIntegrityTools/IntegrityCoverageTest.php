<?php

namespace Unit\app\Domain\DataIntegrityTools;

use Leantime\Domain\DataIntegrityTools\Command\DatabaseIntegrityCheckCommand;
use Leantime\Domain\DataIntegrityTools\Services\IntegrityCheckService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Exercise coverage reporting using fixtures without a database connection.
 */
final class IntegrityCoverageTest extends TestCase
{
    /** @return void */
    public function testServicePreservesResultsAndReportsEverySkippedCheck(): void
    {
        $method = new ReflectionMethod(IntegrityCheckService::class, 'completeChecks');
        foreach ([[], [$this->check(IntegrityCheckService::EXPECTED_CHECKS[0])]] as $executed) {
            $checks = $method->invoke(new IntegrityCheckService(), $executed);
            self::assertCount(3, $checks);
            self::assertSame($executed, array_slice($checks, 0, count($executed)));
            foreach (array_slice($checks, count($executed)) as $check) {
                self::assertSame('skipped', $check['status']);
                self::assertNull($check['count']);
                self::assertStringContainsString('missing prerequisite tables or columns', $check['message']);
            }
        }
    }

    /** @return void */
    public function testStrictModeRejectsIncompleteCoverage(): void
    {
        $complete = array_map($this->check(...), IntegrityCheckService::EXPECTED_CHECKS);
        $skipped = $complete;
        $skipped[1]['status'] = 'skipped';
        $skipped[1]['count'] = null;
        $unsupported = $complete;
        $unsupported[1]['status'] = 'unsupported';
        $unknownCount = $complete;
        $unknownCount[1]['count'] = null;

        foreach ([[], [$complete[0]], $skipped, $unsupported, $unknownCount] as $checks) {
            $command = $this->command($checks);
            self::assertSame(1, $command->handle());
            $summary = json_decode($command->lines[0], true, 512, JSON_THROW_ON_ERROR)['summary'];
            self::assertFalse($summary['coverageComplete']);
            self::assertNotEmpty($summary['incompleteChecks']);
        }
    }

    /** @return void */
    public function testStrictModePassesOnlyCompleteCleanReport(): void
    {
        $checks = array_map($this->check(...), IntegrityCheckService::EXPECTED_CHECKS);
        $command = $this->command($checks);
        self::assertSame(0, $command->handle());
        $summary = json_decode($command->lines[0], true, 512, JSON_THROW_ON_ERROR)['summary'];
        self::assertTrue($summary['coverageComplete']);
        self::assertSame([], $summary['incompleteChecks']);

        self::assertSame(1, $this->command($checks, ['zp_user'])->handle());
        $checks[0]['count'] = 2;
        $checks[0]['status'] = 'warning';
        self::assertSame(1, $this->command($checks)->handle());
    }

    /** @return void */
    public function testAdvisoryModeStillReportsIncompleteCoverage(): void
    {
        $command = $this->command([], strict: false);
        self::assertSame(0, $command->handle());
        $summary = json_decode($command->lines[0], true, 512, JSON_THROW_ON_ERROR)['summary'];
        self::assertFalse($summary['coverageComplete']);
        self::assertSame(IntegrityCheckService::EXPECTED_CHECKS, $summary['incompleteChecks']);
    }

    /** @return void */
    public function testTextOutputDoesNotPresentSkippedCountsAsZero(): void
    {
        $check = $this->check(IntegrityCheckService::EXPECTED_CHECKS[0]);
        $check['status'] = 'skipped';
        $check['count'] = null;
        $command = $this->command([$check], json: false);
        self::assertSame(1, $command->handle());
        self::assertStringContainsString('skipped (count=unknown)', implode("\n", $command->lines));
        self::assertStringContainsString('Coverage: incomplete', implode("\n", $command->lines));
    }

    /** @return array */
    private function check(string $name): array
    {
        return ['name' => $name, 'status' => 'ok', 'count' => 0, 'message' => 'Fixture check'];
    }

    /** @return DatabaseIntegrityCheckCommand */
    private function command(array $checks, array $missingTables = [], bool $strict = true, bool $json = true): DatabaseIntegrityCheckCommand
    {
        $service = new class ($checks, $missingTables) extends IntegrityCheckService {
            /** Bind a deterministic diagnostic report. */
            public function __construct(private array $checks, private array $missingTables)
            {
            }

            /** @return array */
            public function run(string $source = 'target'): array
            {
                return [
                    'source' => $source,
                    'target' => ['host' => 'fixture.invalid', 'port' => '3306', 'database' => 'fixture'],
                    'requiredTables' => ['zp_projects', 'zp_tickets', 'zp_user'],
                    'missingTables' => $this->missingTables,
                    'checks' => $this->checks,
                ];
            }
        };

        return new class ($service, $strict, $json) extends DatabaseIntegrityCheckCommand {
            public array $lines = [];

            /** Bind fixture options and output capture. */
            public function __construct(IntegrityCheckService $service, private bool $strict, private bool $json)
            {
                parent::__construct($service);
            }

            /** @return mixed */
            public function option($key = null)
            {
                return ['source' => 'target', 'json' => $this->json, 'fail-on-errors' => $this->strict][$key];
            }

            /** @return void */
            public function line($string, $style = null, $verbosity = null)
            {
                $this->lines[] = $string;
            }

            /** @return void */
            public function newLine($count = 1)
            {
            }
        };
    }
}
