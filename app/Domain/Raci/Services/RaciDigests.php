<?php

namespace Leantime\Domain\Raci\Services;

use Leantime\Domain\Notifications\Models\Notification;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;

class RaciDigests
{
    public function __construct(
        private SettingRepository $settingsRepo,
    ) {}

    public function queueEntries(array $userIds, Notification $notification, string $cadence): void
    {
        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            if ($userId <= 0 || $userId === (int) $notification->authorId) {
                continue;
            }

            $key = 'usersettings.'.$userId.'.notificationDigests.pending';
            $existing = $this->settingsRepo->getSetting($key, false);
            $entries = [];

            if (is_string($existing) && trim($existing) !== '') {
                $decoded = json_decode($existing, true);
                if (is_array($decoded)) {
                    $entries = $decoded;
                }
            }

            $entity = isset($notification->entity) ? $notification->entity : null;
            $entry = [
                'hash' => md5(implode('|', [
                    $userId,
                    $notification->module,
                    $notification->action,
                    is_array($entity) ? (string) ($entity['moduleId'] ?? $notification->projectId) : (string) $notification->projectId,
                    (string) $notification->subject,
                ])),
                'cadence' => $cadence,
                'module' => $notification->module,
                'action' => $notification->action,
                'projectId' => (int) $notification->projectId,
                'subject' => (string) $notification->subject,
                'message' => (string) $notification->message,
                'url' => is_array($notification->url) ? ($notification->url['url'] ?? '') : '',
                'queuedAt' => date('Y-m-d H:i:s'),
                'authorId' => (int) $notification->authorId,
            ];

            $entries = array_values(array_filter($entries, static fn (array $existingEntry): bool => ($existingEntry['hash'] ?? '') !== $entry['hash']));
            $entries[] = $entry;
            $entries = array_slice($entries, -100);

            $this->settingsRepo->saveSetting($key, json_encode($entries, JSON_THROW_ON_ERROR));
        }
    }
}
