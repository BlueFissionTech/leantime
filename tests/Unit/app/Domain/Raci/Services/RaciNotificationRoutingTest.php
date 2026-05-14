<?php

namespace Unit\app\Domain\Raci\Services;

require_once __DIR__.'/../../../../../../app/Domain/Notifications/Models/Notification.php';
require_once __DIR__.'/../../../../../../app/Domain/Raci/Services/RaciAssignments.php';
require_once __DIR__.'/../../../../../../app/Domain/Raci/Services/RaciNotificationRouting.php';
require_once __DIR__.'/../../../../../../app/Domain/Tickets/Repositories/Tickets.php';

use Leantime\Domain\Notifications\Models\Notification;
use Leantime\Domain\Raci\Services\RaciAssignments;
use Leantime\Domain\Raci\Services\RaciNotificationRouting;
use Leantime\Domain\Tickets\Repositories\Tickets as TicketRepository;
use Unit\TestCase;

class RaciNotificationRoutingTest extends TestCase
{
    public function test_it_routes_responsible_and_accountable_to_cta_consulted_to_info_and_informed_to_digest(): void
    {
        $assignments = $this->createMock(RaciAssignments::class);
        $tickets = $this->createMock(TicketRepository::class);

        $assignments->method('resolveForTicket')->willReturn([
            'responsible' => [5],
            'accountable' => [6],
            'consulted' => [7],
            'informed' => [8],
        ]);

        $notification = new Notification;
        $notification->module = 'tickets';
        $notification->action = 'updated';
        $notification->entity = ['id' => 42, 'type' => 'task', 'projectId' => 11, 'milestoneid' => 0, 'dependingTicketId' => 0];

        $routing = new RaciNotificationRouting($assignments, $tickets);
        $result = $routing->resolveRecipients($notification);

        $this->assertTrue($result['hasScopedAssignments']);
        $this->assertSame([5, 6], $result['cta']);
        $this->assertSame([7], $result['info']);
        $this->assertSame([8], $result['digest']);
        $this->assertSame('daily', $result['cadence']);
    }

    public function test_it_uses_weekly_digest_for_project_updates(): void
    {
        $assignments = $this->createMock(RaciAssignments::class);
        $tickets = $this->createMock(TicketRepository::class);
        $assignments->method('getProjectAssignments')->willReturn([
            'responsible' => [],
            'accountable' => [],
            'consulted' => [],
            'informed' => [9],
        ]);

        $notification = new Notification;
        $notification->module = 'projects';
        $notification->action = 'updated';
        $notification->projectId = 27;

        $routing = new RaciNotificationRouting($assignments, $tickets);
        $result = $routing->resolveRecipients($notification);

        $this->assertSame('weekly', $result['cadence']);
        $this->assertSame([9], $result['digest']);
    }
}
