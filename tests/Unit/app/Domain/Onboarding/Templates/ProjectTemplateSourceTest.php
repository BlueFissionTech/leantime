<?php

namespace Unit\app\Domain\Onboarding\Templates;

use Unit\TestCase;

class ProjectTemplateSourceTest extends TestCase
{
    public function test_project_template_contains_launcher_runner_and_review_sections(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Onboarding/Templates/project.blade.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('Discovery Intake', $template);
        $this->assertStringContainsString('Survey Runner', $template);
        $this->assertStringContainsString('Review + Preview', $template);
        $this->assertStringContainsString('Save Draft to Manifest', $template);
        $this->assertStringContainsString('Apply Reviewed Sync', $template);
        $this->assertStringContainsString('Write/apply calls are intentionally disabled', $template);
    }
}
