<?php

namespace Unit\app\Domain\Onboarding\Services;

use Unit\TestCase;

class ManifestOnboardingSourceTest extends TestCase
{
    public function test_manifest_onboarding_service_uses_expected_command_paths_and_write_flag(): void
    {
        $service = file_get_contents(__DIR__.'/../../../../../../app/Domain/Onboarding/Services/ManifestOnboarding.php');

        $this->assertIsString($service);
        $this->assertStringContainsString("/api/questionnaire/template-export", $service);
        $this->assertStringContainsString("/api/questionnaire/session-read", $service);
        $this->assertStringContainsString("/api/questionnaire/draft-read", $service);
        $this->assertStringContainsString("/api/questionnaire/sync-preview", $service);
        $this->assertStringContainsString("/api/questionnaire/session-upsert", $service);
        $this->assertStringContainsString("/api/questionnaire/sync-apply", $service);
        $this->assertStringContainsString("LEAN_MANIFEST_WRITE_ENABLED", $service);
        $this->assertStringContainsString('withHeaders([\'x-api-key\' => $apiKey])', $service);
    }
}
