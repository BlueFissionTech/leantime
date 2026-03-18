<?php

namespace Unit\app\Domain\Ticketdependencies\Services;

require_once __DIR__.'/../../../../../../app/Core/Support/EntityRelationshipEnum.php';
require_once __DIR__.'/../../../../../../app/Domain/Ticketdependencies/Support/BlockedState.php';
require_once __DIR__.'/../../../../../../app/Domain/Ticketdependencies/Repositories/Ticketdependencies.php';
require_once __DIR__.'/../../../../../../app/Domain/Ticketdependencies/Services/Ticketdependencies.php';
require_once __DIR__.'/../../../../../../app/Domain/Tickets/Models/Tickets.php';

use Leantime\Domain\Ticketdependencies\Repositories\Ticketdependencies as TicketdependenciesRepository;
use Leantime\Domain\Ticketdependencies\Services\Ticketdependencies;
use Leantime\Domain\Ticketdependencies\Support\BlockedState;
use Leantime\Domain\Tickets\Models\Tickets as TicketModel;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Unit\TestCase;

class TicketdependenciesTest extends TestCase
{
    public function test_it_builds_a_blocked_map_from_dependency_statuses(): void
    {
        $repository = $this->createMock(TicketdependenciesRepository::class);
        $ticketRepository = $this->createMock(TicketRepository::class);

        $repository->method('getDependencyStatusesForTickets')->willReturn([
            10 => ['4'],
            11 => ['0'],
        ]);

        $service = new Ticketdependencies($repository, $ticketRepository, new BlockedState);
        $statusLabels = [
            4 => ['statusType' => 'INPROGRESS'],
            0 => ['statusType' => 'DONE'],
        ];

        $blockedMap = $service->getBlockedTicketMap([10, 11, 12], $statusLabels);

        $this->assertTrue($blockedMap[10]);
        $this->assertFalse($blockedMap[11]);
        $this->assertFalse($blockedMap[12]);
    }

    public function test_it_coerces_active_status_to_blocked_when_dependencies_are_incomplete(): void
    {
        $repository = $this->createMock(TicketdependenciesRepository::class);
        $ticketRepository = $this->createMock(TicketRepository::class);

        $repository->method('getDependencyStatusesForTickets')->willReturn([
            10 => ['4'],
        ]);

        $service = new Ticketdependencies($repository, $ticketRepository, new BlockedState);
        $statusLabels = [
            1 => ['name' => 'status.blocked', 'statusType' => 'INPROGRESS'],
            4 => ['name' => 'status.in_progress', 'statusType' => 'INPROGRESS'],
            0 => ['name' => 'status.done', 'statusType' => 'DONE'],
        ];

        $this->assertSame(1, $service->coerceBlockedStatus(10, 4, $statusLabels));
        $this->assertSame(1, $service->coerceBlockedStatus(10, 0, $statusLabels));
    }

    public function test_it_filters_and_syncs_dependency_ids_for_the_same_project(): void
    {
        $repository = $this->createMock(TicketdependenciesRepository::class);
        $ticketRepository = $this->createMock(TicketRepository::class);

        $ticket = new TicketModel(['id' => 15, 'projectId' => 22]);
        $ticketRepository->method('getTicket')->with(15)->willReturn($ticket);
        $repository->expects($this->once())
            ->method('getValidDependencyIds')
            ->with(15, 22, [4, 7, 7, 15])
            ->willReturn([4, 7]);
        $repository->expects($this->once())
            ->method('syncDependencies')
            ->with(15, [4, 7], 9)
            ->willReturn(true);

        $service = new Ticketdependencies($repository, $ticketRepository, new BlockedState);

        $this->assertTrue($service->syncDependencies(15, [4, 7, 7, 15], 9));
    }
}
