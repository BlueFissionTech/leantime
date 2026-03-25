<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class CommentsApiSourceTest extends TestCase
{
    public function test_comments_api_controller_supports_ticket_comment_create(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Comments.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("Only module=ticket is currently supported", $controller);
        $this->assertStringContainsString("createComment(\$params, 'ticket', \$ticketId, \$ticket)", $controller);
        $this->assertStringContainsString("displayJson(['status' => 'ok', 'result' => \$createdComment], 201)", $controller);
    }
}
