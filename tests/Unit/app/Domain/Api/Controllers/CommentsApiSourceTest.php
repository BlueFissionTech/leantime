<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class CommentsApiSourceTest extends TestCase
{
    public function test_comments_api_controller_supports_ticket_and_project_comment_create(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Comments.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("Only module=ticket or module=project is currently supported", $controller);
        $this->assertStringContainsString("module=ticket|project and moduleId are required", $controller);
        $this->assertStringContainsString("'project' => \$this->projectService->getProject(\$moduleId)", $controller);
        $this->assertStringContainsString("status must be green, yellow, or red", $controller);
        $this->assertStringContainsString("createComment(\$params, \$module, \$moduleId, \$entity)", $controller);
        $this->assertStringContainsString("displayJson(['status' => 'ok', 'result' => \$createdComment], 201)", $controller);
    }
}
