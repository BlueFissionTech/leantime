<?php

namespace Leantime\Domain\Menu\Support;

class NotificationSegments
{
    /**
     * @param  array<int, array<string, mixed>>  $notifications
     * @return array<string, mixed>
     */
    public static function partition(array $notifications): array
    {
        $segments = [
            'mentions' => [],
            'comments' => [],
            'activity' => [],
            'counts' => [
                'unreadTotal' => 0,
                'totalNotificationCount' => 0,
                'totalActivityCount' => 0,
                'totalCommentCount' => 0,
                'totalMentionCount' => 0,
                'totalNewActivity' => 0,
                'totalNewComments' => 0,
                'totalNewMentions' => 0,
                'totalNewNotifications' => 0,
            ],
        ];

        foreach ($notifications as $notification) {
            $isUnread = (int) ($notification['read'] ?? 1) === 0;
            $isMention = ($notification['type'] ?? '') === 'mention';

            if ($isUnread) {
                $segments['counts']['unreadTotal']++;
            }

            if ($isMention) {
                $segments['mentions'][] = $notification;
                $segments['counts']['totalMentionCount']++;

                if ($isUnread) {
                    $segments['counts']['totalNewMentions']++;
                }

                continue;
            }

            $segments['counts']['totalNotificationCount']++;

            if ($isUnread) {
                $segments['counts']['totalNewNotifications']++;
            }

            if (($notification['module'] ?? '') === 'comments') {
                $segments['comments'][] = $notification;
                $segments['counts']['totalCommentCount']++;

                if ($isUnread) {
                    $segments['counts']['totalNewComments']++;
                }
            } else {
                $segments['activity'][] = $notification;
                $segments['counts']['totalActivityCount']++;

                if ($isUnread) {
                    $segments['counts']['totalNewActivity']++;
                }
            }
        }

        return $segments;
    }
}
