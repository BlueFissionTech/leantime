<?php

namespace Unit\app\Domain\Supportcenter\Services;

require_once __DIR__.'/../../../../../../app/Domain/Supportcenter/Services/GithubElevation.php';

use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;
use Leantime\Domain\Supportcenter\Services\GithubElevation;
use Unit\TestCase;

class GithubElevationTest extends TestCase
{
    public function test_it_returns_false_when_no_github_issue_exists(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(false);

        $service = new GithubElevation($settings);

        $this->assertFalse($service->getTicketGithubStatus(123));
    }

    public function test_it_maps_open_issues_to_in_progress_without_github_config(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(json_encode([
            'state' => 'open',
        ]));

        $service = new GithubElevation($settings);

        $this->assertSame([
            'status' => 'In Progress',
            'isDone' => false,
            'isInProgress' => true,
        ], $service->getTicketGithubStatus(123));
    }

    public function test_it_maps_closed_issues_to_done_without_github_config(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(json_encode([
            'state' => 'closed',
        ]));

        $service = new GithubElevation($settings);

        $this->assertSame([
            'status' => 'Done',
            'isDone' => true,
            'isInProgress' => false,
        ], $service->getTicketGithubStatus(123));
    }
}
