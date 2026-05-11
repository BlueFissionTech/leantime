<?php

namespace Tests\Unit\app\Domain\Comments;

use Unit\TestCase;

class CommentSpacingSourceTest extends TestCase
{
    private function repoPath(string $relativePath): string
    {
        return realpath(__DIR__.'/../../../../../'.$relativePath) ?: __DIR__.'/../../../../../'.$relativePath;
    }

    public function test_simple_tiptap_editor_has_explicit_enter_and_shift_enter_shortcuts(): void
    {
        $source = file_get_contents($this->repoPath('public/assets/js/app/core/tiptap/index.js'));

        $this->assertIsString($source);
        $this->assertStringContainsString("name: 'simpleCommentSpacing'", $source);
        $this->assertStringContainsString("Enter: () => this.editor.commands.first", $source);
        $this->assertStringContainsString("'Shift-Enter': () => this.editor.commands.setHardBreak()", $source);
        $this->assertStringContainsString("if (options.toolbar === 'simple')", $source);
    }

    public function test_rendered_comment_content_keeps_paragraph_spacing(): void
    {
        $source = file_get_contents($this->repoPath('public/assets/css/components/style.default.css'));

        $this->assertIsString($source);
        $this->assertStringContainsString('.commentContent .tiptap-content p {', $source);
        $this->assertStringContainsString('.commentContent .tiptap-content p:last-child {', $source);
    }
}
