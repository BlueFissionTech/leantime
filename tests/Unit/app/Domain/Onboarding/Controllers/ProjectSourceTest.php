<?php

namespace Unit\app\Domain\Onboarding\Controllers;

use Unit\TestCase;

class ProjectSourceTest extends TestCase
{
    public function test_project_onboarding_controller_calls_manifest_contract_methods(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Onboarding/Controllers/Project.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("templateExport(", $controller);
        $this->assertStringContainsString("sessionRead(", $controller);
        $this->assertStringContainsString("draftRead(", $controller);
        $this->assertStringContainsString("syncPreview(", $controller);
        $this->assertStringContainsString("sessionUpsert(", $controller);
        $this->assertStringContainsString("syncApply(", $controller);
        $this->assertStringContainsString("buildExternalRef", $controller);
        $this->assertStringContainsString("currentUrl", $controller);
    }
}
