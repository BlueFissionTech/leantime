<?php

namespace Unit\app\Domain\Raci\Services;

require_once __DIR__.'/../../../../../../app/Domain/Setting/Services/SettingCache.php';
require_once __DIR__.'/../../../../../../app/Domain/Setting/Repositories/Setting.php';
require_once __DIR__.'/../../../../../../app/Domain/Tickets/Repositories/Tickets.php';
require_once __DIR__.'/../../../../../../app/Domain/Raci/Services/RaciAssignments.php';

use Leantime\Domain\Raci\Services\RaciAssignments;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Unit\TestCase;

class RaciAssignmentsTest extends TestCase
{
    public function test_it_normalizes_and_saves_project_assignments(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $tickets = $this->createMock(TicketRepository::class);

        $settings->expects($this->once())
            ->method('saveSetting')
            ->with(
                'projectsettings.27.raciAssignments',
                json_encode([
                    'responsible' => [5, 8],
                    'accountable' => [11],
                    'consulted' => [],
                    'informed' => [14],
                ], JSON_THROW_ON_ERROR)
            );

        $service = new RaciAssignments($settings, $tickets);
        $service->saveProjectAssignments(27, [
            'raciResponsible' => ['5', '8', '8'],
            'raciAccountable' => ['11'],
            'raciInformed' => ['14'],
        ]);
    }

    public function test_ticket_resolution_prefers_task_then_parent_then_milestone_then_project(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $tickets = $this->createMock(TicketRepository::class);

        $settings->method('getSetting')->willReturnMap([
            ['projectsettings.9.raciAssignments', false, json_encode([
                'responsible' => [1],
                'accountable' => [],
                'consulted' => [],
                'informed' => [7],
            ], JSON_THROW_ON_ERROR)],
            ['milestonesettings.22.raciAssignments', false, json_encode([
                'responsible' => [2],
                'accountable' => [3],
                'consulted' => [],
                'informed' => [],
            ], JSON_THROW_ON_ERROR)],
            ['ticketsettings.14.raciAssignments', false, json_encode([
                'responsible' => [],
                'accountable' => [],
                'consulted' => [4],
                'informed' => [],
            ], JSON_THROW_ON_ERROR)],
            ['ticketsettings.15.raciAssignments', false, json_encode([
                'responsible' => [5],
                'accountable' => [],
                'consulted' => [],
                'informed' => [6],
            ], JSON_THROW_ON_ERROR)],
        ]);

        $service = new RaciAssignments($settings, $tickets);

        $resolved = $service->resolveForTicket((object) [
            'id' => 15,
            'type' => 'subtask',
            'projectId' => 9,
            'milestoneid' => 22,
            'dependingTicketId' => 14,
        ]);

        $this->assertSame([5], $resolved['responsible']);
        $this->assertSame([3], $resolved['accountable']);
        $this->assertSame([4], $resolved['consulted']);
        $this->assertSame([6], $resolved['informed']);
        $this->assertSame('task', $resolved['_sources']['responsible']);
        $this->assertSame('milestone', $resolved['_sources']['accountable']);
        $this->assertSame('parent_task', $resolved['_sources']['consulted']);
        $this->assertSame('task', $resolved['_sources']['informed']);
    }

    public function test_display_assignments_include_names_and_sources(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $tickets = $this->createMock(TicketRepository::class);
        $service = new RaciAssignments($settings, $tickets);

        $display = $service->toDisplayAssignments([
            'responsible' => [2],
            'accountable' => [],
            'consulted' => [3, 4],
            'informed' => [],
            '_sources' => [
                'responsible' => 'task',
                'consulted' => 'project',
            ],
        ], [
            ['id' => 2, 'firstname' => 'Devon', 'lastname' => 'Scott'],
            ['id' => 3, 'firstname' => 'Avery', 'lastname' => 'Lane'],
            ['id' => 4, 'firstname' => 'Morgan', 'lastname' => 'Reed'],
        ]);

        $this->assertSame(['Devon Scott'], $display['responsible']['names']);
        $this->assertSame('task', $display['responsible']['source']);
        $this->assertSame(['Avery Lane', 'Morgan Reed'], $display['consulted']['names']);
        $this->assertSame('project', $display['consulted']['source']);
    }
}
