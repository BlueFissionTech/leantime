<?php

namespace Unit\app\Domain\Widgets\Templates;

use Unit\TestCase;

class HighImpactNextTemplateTest extends TestCase
{
    public function test_template_removes_advisory_helper_copy_from_header(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Templates/partials/highImpactNext.blade.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString("__('text.high_impact_next_helper')", $source);
    }

    public function test_template_wraps_long_badges_and_ticket_text(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Templates/partials/highImpactNext.blade.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('tw-whitespace-normal tw-break-all', $source);
        $this->assertStringContainsString('tw-break-words', $source);
        $this->assertStringContainsString('tw-flex-wrap', $source);
    }
}
