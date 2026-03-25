<?php

namespace Unit\app\Domain\Menu\Support;

require_once __DIR__.'/../../../../../../app/Domain/Menu/Support/NotificationSegments.php';

use Leantime\Domain\Menu\Support\NotificationSegments;
use PHPUnit\Framework\TestCase;

class NotificationSegmentsTest extends TestCase
{
    public function test_partition_splits_mentions_comments_and_activity(): void
    {
        $notifications = [
            ['id' => 1, 'type' => 'mention', 'module' => 'comments', 'read' => 0],
            ['id' => 2, 'type' => 'notification', 'module' => 'comments', 'read' => 0],
            ['id' => 3, 'type' => 'notification', 'module' => 'tickets', 'read' => 1],
            ['id' => 4, 'type' => 'notification', 'module' => 'ideas', 'read' => 0],
        ];

        $segments = NotificationSegments::partition($notifications);

        $this->assertSame([1], array_column($segments['mentions'], 'id'));
        $this->assertSame([2], array_column($segments['comments'], 'id'));
        $this->assertSame([3, 4], array_column($segments['activity'], 'id'));

        $this->assertSame(3, $segments['counts']['unreadTotal']);
        $this->assertSame(1, $segments['counts']['totalMentionCount']);
        $this->assertSame(1, $segments['counts']['totalNewMentions']);
        $this->assertSame(3, $segments['counts']['totalNotificationCount']);
        $this->assertSame(2, $segments['counts']['totalNewNotifications']);
    }

    public function test_partition_handles_missing_fields_defensively(): void
    {
        $segments = NotificationSegments::partition([
            ['id' => 5],
        ]);

        $this->assertSame([], $segments['mentions']);
        $this->assertSame([], $segments['comments']);
        $this->assertSame([5], array_column($segments['activity'], 'id'));
        $this->assertSame(0, $segments['counts']['unreadTotal']);
        $this->assertSame(1, $segments['counts']['totalNotificationCount']);
    }
}
