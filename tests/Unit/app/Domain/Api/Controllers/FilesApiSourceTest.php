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
        $this->assertStringContainsString("\$this->fileService->getFile(\$fileId)", $controller);
        $this->assertStringContainsString("\$updates = \$this->fileService->getApiMetadataUpdates(\$params);", $controller);
        $this->assertStringContainsString("No supported file metadata fields were supplied", $controller);
        $this->assertStringContainsString("\$this->fileService->updateFile(\$fileId, \$updates)", $controller);
        $this->assertStringContainsString("Missing file id", $controller);
        $this->assertStringContainsString("\$this->fileService->deleteFile(\$fileId)", $controller);
    }

    public function test_files_service_exposes_api_metadata_update_filter(): void
    {
        $service = file_get_contents(__DIR__.'/../../../../../../app/Domain/Files/Services/Files.php');

        $this->assertIsString($service);
        $this->assertStringContainsString('public function getApiMetadataUpdates(array $values): array', $service);
        $this->assertStringContainsString("if (array_key_exists('realName', \$values))", $service);
        $this->assertStringContainsString("if (array_key_exists('module', \$values))", $service);
        $this->assertStringContainsString("if (array_key_exists('moduleId', \$values))", $service);
    }
}
