<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class DocsApiSourceTest extends TestCase
{
    public function test_docs_api_controller_supports_wiki_board_and_article_crud(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Docs.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("if (isset(\$params['projectId']))", $controller);
        $this->assertStringContainsString("if (isset(\$params['canvasId']))", $controller);
        $this->assertStringContainsString("if (\$params['action'] === 'createBoard')", $controller);
        $this->assertStringContainsString("if (\$params['action'] === 'createItem')", $controller);
        $this->assertStringContainsString("\$this->wikiRepository->delWiki((int) \$params['boardId'])", $controller);
        $this->assertStringContainsString("\$this->wikiRepository->delArticle((int) \$params['id'])", $controller);
    }
}
