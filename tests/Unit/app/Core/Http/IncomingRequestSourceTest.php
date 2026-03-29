<?php

namespace Tests\Unit\App\Core\Http;

use PHPUnit\Framework\TestCase;

class IncomingRequestSourceTest extends TestCase
{
    public function test_patch_request_params_supports_json_payloads(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../app/Core/Http/IncomingRequest.php');

        $this->assertNotFalse($source);
        $this->assertStringContainsString("if (str_contains(\$contentType, 'application/json'))", $source);
        $this->assertStringContainsString("\$decoded = json_decode(\$content, true);", $source);
        $this->assertStringContainsString('if (is_array($decoded)) {', $source);
        $this->assertStringContainsString('$patch_vars = $decoded;', $source);
        $this->assertStringContainsString('parse_str($content, $patch_vars);', $source);
    }
}
