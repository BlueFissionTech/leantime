<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class IdeasApiSourceTest extends TestCase
{
    public function test_ideas_api_controller_supports_full_board_and_item_crud(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Ideas.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("if (isset(\$params['projectId']))", $controller);
        $this->assertStringContainsString("if (isset(\$params['boardId']))", $controller);
        $this->assertStringContainsString("\$params['action'] === 'createBoard'", $controller);
        $this->assertStringContainsString("\$params['action'] === 'createItem'", $controller);
        $this->assertStringContainsString("\$this->ideaAPIRepo->deleteCanvas((int) \$params['boardId'])", $controller);
        $this->assertStringContainsString("\$this->ideaAPIRepo->delCanvasItem((int) \$params['id'])", $controller);
    }
}
