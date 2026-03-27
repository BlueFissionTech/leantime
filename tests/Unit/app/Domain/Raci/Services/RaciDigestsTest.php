<?php

namespace Unit\app\Domain\Raci\Services;

require_once __DIR__.'/../../../../../../app/Domain/Notifications/Models/Notification.php';
require_once __DIR__.'/../../../../../../app/Domain/Setting/Repositories/Setting.php';
require_once __DIR__.'/../../../../../../app/Domain/Setting/Services/SettingCache.php';
require_once __DIR__.'/../../../../../../app/Domain/Raci/Services/RaciDigests.php';

use Leantime\Domain\Notifications\Models\Notification;
use Leantime\Domain\Raci\Services\RaciDigests;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Unit\TestCase;

class RaciDigestsTest extends TestCase
{
    public function test_it_stores_digest_entries_for_informed_users(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(false);
        $settings->expects($this->once())
            ->method('saveSetting')
            ->with(
                'usersettings.12.notificationDigests.pending',
                $this->callback(function (string $json): bool {
                    $decoded = json_decode($json, true);

                    return is_array($decoded)
                        && count($decoded) === 1
                        && ($decoded[0]['cadence'] ?? '') === 'weekly'
                        && ($decoded[0]['module'] ?? '') === 'projects';
                })
            );

        $notification = new Notification;
        $notification->module = 'projects';
        $notification->action = 'updated';
        $notification->projectId = 27;
        $notification->subject = 'Project updated';
        $notification->message = 'A project update happened.';
        $notification->url = ['url' => 'https://example.test/project/27', 'text' => 'Open'];
        $notification->authorId = 4;

        $service = new RaciDigests($settings);
        $service->queueEntries([12], $notification, 'weekly');
    }
}
