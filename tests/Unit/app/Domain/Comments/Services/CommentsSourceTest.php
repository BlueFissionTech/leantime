<?php

namespace Unit\app\Domain\Comments\Services;

use Unit\TestCase;

class CommentsSourceTest extends TestCase
{
    public function test_comments_service_exposes_structured_create_comment_result(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Comments/Services/Comments.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('public function createComment', $source);
        $this->assertStringContainsString("'commentParent' => (int) \$commentParent", $source);
        $this->assertStringContainsString("'moduleId' => (int) \$entityId", $source);
    }
}
