<?php

namespace Unit\app\Domain\Tickets\Support;

use Carbon\CarbonImmutable;
use Leantime\Domain\Tickets\Services\Tickets;
use Leantime\Domain\Tickets\Support\ProjectSheetExportFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

require_once __DIR__.'/../../../../../../app/Domain/Tickets/Support/ProjectSheetExportFormatter.php';
require_once __DIR__.'/../../../../../../app/Domain/Tickets/Services/Tickets.php';

class ProjectSheetExportEvidenceTest extends TestCase
{
    private function service(array|false $tickets, bool $expectRead = true): Tickets
    {
        $service = $this->getMockBuilder(Tickets::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepareTicketSearchArray', 'getAll'])
            ->getMock();
        $service->method('prepareTicketSearchArray')->willReturn(['status' => 'open']);
        $service->expects($expectRead ? $this->once() : $this->never())
            ->method('getAll')
            ->with(['status' => 'open', 'currentProject' => 26], 10000)
            ->willReturn($tickets);
        (new ReflectionProperty(Tickets::class, 'projectSheetExportFormatter'))
            ->setValue($service, new ProjectSheetExportFormatter);

        return $service;
    }

    public function test_empty_success_is_distinct_from_failed_read(): void
    {
        $empty = $this->service([])->getProjectSheetExportWithEvidence(['projectId' => 26]);
        $failed = $this->service(false)->getProjectSheetExportWithEvidence(['projectId' => 26]);

        $this->assertSame([], $empty['rows']);
        $this->assertSame(0, $empty['count']);
        $this->assertTrue($empty['evidence']['queryComplete']);
        $this->assertSame(0, $empty['evidence']['retrievedCount']);
        $this->assertNull($empty['evidence']['reason']);
        $this->assertFalse($failed['evidence']['queryComplete']);
        $this->assertNull($failed['evidence']['retrievedCount']);
        $this->assertSame('source_unavailable', $failed['evidence']['reason']);
    }

    public function test_limit_is_detected_before_delta_filtering(): void
    {
        $rows = array_fill(0, 10000, ['id' => 1, 'projectId' => 26, 'modified' => '2026-01-01']);
        $result = $this->service($rows)->getProjectSheetExportWithEvidence([
            'projectId' => 26,
            'updated_since' => '2026-02-01',
        ]);

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['count']);
        $this->assertFalse($result['evidence']['queryComplete']);
        $this->assertSame('limit_reached', $result['evidence']['reason']);
        $this->assertSame(10000, $result['evidence']['retrievedCount']);
    }

    public function test_success_retains_rows_and_scopes_evidence_to_the_query(): void
    {
        $before = CarbonImmutable::now('UTC');
        $result = $this->service([['id' => 12, 'projectId' => 26]])
            ->getProjectSheetExportWithEvidence(['projectId' => 26]);

        $this->assertSame(12, $result['rows'][0]['todoId']);
        $this->assertSame(26, $result['projectId']);
        $this->assertSame(1, $result['count']);
        $this->assertSame(1, $result['evidence']['schemaVersion']);
        $this->assertSame('filtered_project_query', $result['evidence']['scope']);
        $this->assertTrue($result['evidence']['queryComplete']);
        $observed = CarbonImmutable::parse($result['evidence']['observedAt']);
        $this->assertTrue($observed->betweenIncluded($before->startOfSecond(), CarbonImmutable::now('UTC')));
    }

    public function test_invalid_project_does_not_read_records(): void
    {
        $result = $this->service([], false)->getProjectSheetExportWithEvidence([]);

        $this->assertSame('invalid_project', $result['evidence']['reason']);
        $this->assertFalse($result['evidence']['queryComplete']);
        $this->assertNull($result['evidence']['retrievedCount']);
    }

    public function test_invalid_cursor_does_not_silently_export_all_records(): void
    {
        $result = $this->service([], false)->getProjectSheetExportWithEvidence([
            'projectId' => 26,
            'updatedSince' => 'definitely-not-a-date',
        ]);

        $this->assertSame('invalid_updated_since', $result['evidence']['reason']);
        $this->assertFalse($result['evidence']['queryComplete']);
        $this->assertSame([], $result['rows']);
    }

    public function test_legacy_service_still_returns_only_rows(): void
    {
        $rows = $this->service([['id' => 12, 'projectId' => 26]])
            ->getProjectSheetExport(['projectId' => 26]);

        $this->assertCount(1, $rows);
        $this->assertSame(12, $rows[0]['todoId']);
        $this->assertArrayNotHasKey('evidence', $rows);
    }
}
