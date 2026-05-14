<?php

namespace Leantime\Domain\Notifications\Listeners;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Domain\Notifications\Services\Notifications;

class NotifyProjectUsers
{
    /**
     * @throws BindingResolutionException
     */
    public function handle($payload): void
    {

        $notificationService = app()->make(Notifications::class);

        $notifications = [];

        if (isset($payload['notifications']) && is_array($payload['notifications'])) {
            foreach ($payload['notifications'] as $notificationPayload) {
                if (! isset($notificationPayload['user']['id'])) {
                    continue;
                }

                $notifications[] = [
                    'userId' => $notificationPayload['user']['id'],
                    'type' => $payload['type'],
                    'module' => $payload['module'],
                    'moduleId' => $payload['moduleId'],
                    'message' => $notificationPayload['message'] ?? $payload['message'],
                    'datetime' => date('Y-m-d H:i:s'),
                    'url' => $notificationPayload['url'] ?? '',
                    'authorId' => session('userdata.id'),
                ];
            }
        } else {
        foreach ($payload['users'] as $user) {
            $notifications[] = [
                'userId' => $user['id'],
                'type' => $payload['type'],
                'module' => $payload['module'],
                'moduleId' => $payload['moduleId'],
                'message' => $payload['message'],
                'datetime' => date('Y-m-d H:i:s'),
                'url' => $payload['url'],
                'authorId' => session('userdata.id'),
            ];
        }
        }

        $notificationService->addNotifications($notifications);
    }
}
