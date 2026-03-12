<?php

namespace Unit\app\Core\UI;

use Leantime\Core\UI\Template;

class TemplateTest extends \Unit\TestCase
{
    private Template $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->template = $this->getMockBuilder(Template::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function test_escape_comment_preserves_plain_text_line_breaks(): void
    {
        $rendered = $this->template->escapeComment("First line\nSecond line");

        $this->assertStringContainsString("First line<br>\nSecond line", $rendered);
    }

    public function test_escape_comment_caps_excessive_blank_lines(): void
    {
        $rendered = $this->template->escapeComment("First line\n\n\n\nSecond line");

        $this->assertStringNotContainsString("\n\n\n\n", $rendered);
        $this->assertStringContainsString('Second line', $rendered);
    }

    public function test_escape_comment_keeps_rich_text_paragraphs(): void
    {
        $rendered = $this->template->escapeComment('<p>First paragraph</p><p>Second paragraph</p>');

        $this->assertSame('<p>First paragraph</p><p>Second paragraph</p>', $rendered);
    }

    public function test_escape_comment_still_sanitizes_unsafe_html(): void
    {
        $rendered = $this->template->escapeComment('<script>alert(1)</script><p>Safe</p>');

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('<p>Safe</p>', $rendered);
    }
}
