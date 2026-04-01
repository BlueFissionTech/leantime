<?php

namespace Unit\app\Domain\Api\Controllers;

use Unit\TestCase;

class FilesApiSourceTest extends TestCase
{
    public function test_files_api_controller_supports_lookup_upload_update_and_delete(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Api/Controllers/Files.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("if (isset(\$params['id']))", $controller);
        $this->assertStringContainsString("if (isset(\$params['module']))", $controller);
        $this->assertStringContainsString("if (isset(\$_FILES['file']) && \$module !== null && \$id !== null)", $controller);
        $this->assertStringContainsString("\$this->fileService->updateFile((int) \$params['id'], \$params)", $controller);
        $this->assertStringContainsString("\$this->fileService->deleteFile((int) \$params['id'])", $controller);
    }
}
