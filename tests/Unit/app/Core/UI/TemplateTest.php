<?php

namespace Unit\app\Core\UI;

use Leantime\Core\UI\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    private Template $template;

    protected function setUp(): void
    {
        if (! defined('BASE_URL')) {
            define('BASE_URL', 'http://localhost');
        }

        $reflection = new \ReflectionClass(Template::class);
        $this->template = $reflection->newInstanceWithoutConstructor();
    }

    public function test_escape_minimal_rewrites_local_files_get_urls_to_current_base_url(): void
    {
        $content = '<p>Image</p><img src="https://support.example.com/files/get?module=project&amp;encName=abc123&amp;ext=png&amp;realName=Example%20Image.png" alt="Example" />';

        $result = $this->template->escapeMinimal($content);

        $this->assertStringContainsString(
            'src="http://localhost/files/get?module=project&amp;encName=abc123&amp;ext=png&amp;realName=Example%20Image.png"',
            $result
        );
    }

    public function test_escape_minimal_leaves_external_images_untouched(): void
    {
        $content = '<img src="https://cdn.example.com/images/example.png" alt="Example" />';

        $result = $this->template->escapeMinimal($content);

        $this->assertStringContainsString(
            'src="https://cdn.example.com/images/example.png"',
            $result
        );
    }
}
