<?php

namespace Unit\app\Domain\Supportcenter\Services;

require_once __DIR__.'/../../../../../../app/Domain/Supportcenter/Services/GithubElevation.php';

use Illuminate\Support\Facades\Http;
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

    public function test_it_maps_open_issues_to_backlog_without_github_config(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(json_encode([
            'state' => 'open',
        ]));

        $service = new GithubElevation($settings);

        $this->assertSame([
            'status' => 'Backlog',
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

    public function test_it_returns_explicit_repo_visibility_error_before_issue_create(): void
    {
        putenv('LEAN_SUPPORT_GITHUB_REPO=BlueFissionTech/morpro');
        putenv('LEAN_SUPPORT_GITHUB_TOKEN=test-token');
        putenv('LEAN_SUPPORT_GITHUB_BASE_URL=https://api.github.com');
        $_ENV['LEAN_SUPPORT_GITHUB_REPO'] = 'BlueFissionTech/morpro';
        $_ENV['LEAN_SUPPORT_GITHUB_TOKEN'] = 'test-token';
        $_ENV['LEAN_SUPPORT_GITHUB_BASE_URL'] = 'https://api.github.com';

        Http::fake([
            'https://api.github.com/repos/BlueFissionTech/morpro' => Http::response(
                ['message' => 'Not Found'],
                404,
                ['X-GitHub-Request-Id' => 'REQ123']
            ),
        ]);

        $settings = $this->createMock(SettingRepository::class);
        $settings->method('getSetting')->willReturn(false);

        $service = new GithubElevation($settings);
        $result = $service->createGithubIssue(123, (object) ['headline' => 'Sample'], [
            'githubTitle' => 'Sample title',
            'githubSummary' => 'Sample summary',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('GitHub repository BlueFissionTech/morpro is not visible to the configured token.', $result['message']);
        $this->assertStringContainsString('REQ123', $result['message']);
    }

    public function test_it_builds_default_title_and_plain_text_summary_from_ticket_content(): void
    {
        $settings = $this->createMock(SettingRepository::class);
        $service = new GithubElevation($settings);
        $ticket = (object) [
            'headline' => 'Need multi-stop load support',
            'description' => '<p>User should be able to add stops to a load.</p><p><strong>Many loads are more than 1 pick-1 drop.</strong></p><img src="https://support.example.com/files/get?module=project&amp;encName=abc123&amp;ext=png&amp;realName=Stops.png" alt="Stops screenshot">',
        ];

        $this->assertSame('Need multi-stop load support', $service->getDefaultGithubTitle($ticket));
        $this->assertSame(
            "User should be able to add stops to a load.\nMany loads are more than 1 pick-1 drop.\n\n![Stops screenshot](http://localhost/files/get?module=project&encName=abc123&ext=png&realName=Stops.png)",
            $service->getDefaultGithubSummary($ticket)
        );
    }
}
