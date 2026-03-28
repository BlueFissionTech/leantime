<?php

namespace Unit\app\Domain\Comments\Repositories;

use Unit\TestCase;

class ProjectStatusUpdatesSourceTest extends TestCase
{
    public function test_comments_repository_supports_recent_project_status_updates_query(): void
    {
        $repository = file_get_contents(__DIR__.'/../../../../../../app/Domain/Comments/Repositories/Comments.php');

        $this->assertIsString($repository);
        $this->assertStringContainsString('function getRecentProjectStatusUpdates', $repository);
        $this->assertStringContainsString("where('comment.module', 'project')", $repository);
        $this->assertStringContainsString("where('comment.commentParent', 0)", $repository);
        $this->assertStringContainsString("where('comment.status', '<>', '')", $repository);
        $this->assertStringContainsString("join('zp_projects as project'", $repository);
    }
}
