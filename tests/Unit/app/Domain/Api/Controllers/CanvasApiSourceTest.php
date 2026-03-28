<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class CanvasApiSourceTest extends TestCase
{
    public function test_canvas_api_controller_supports_normalized_crud_paths(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Canvas.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("if (isset(\$params['projectId']))", $controller);
        $this->assertStringContainsString("if (\$params['action'] === 'createBoard')", $controller);
        $this->assertStringContainsString("if (\$params['action'] === 'createItem')", $controller);
        $this->assertStringContainsString("if (isset(\$params['boardId']))", $controller);
        $this->assertStringContainsString("\$this->canvasRepo->delCanvasItem((int) \$params['id'])", $controller);
        $this->assertStringContainsString("\$this->canvasRepo->deleteCanvas((int) \$params['boardId'])", $controller);
    }
}
