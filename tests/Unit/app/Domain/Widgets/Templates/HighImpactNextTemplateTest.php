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
        $this->assertStringContainsString('tw-whitespace-normal tw-break-all tw-align-top', $source);
        $this->assertStringContainsString('tw-break-words', $source);
        $this->assertStringContainsString('tw-inline-block tw-mr-xs tw-mb-xs', $source);
        $this->assertStringNotContainsString('tw-flex tw-flex-wrap tw-items-center tw-gap-xs', $source);
    }

    public function test_template_uses_explosion_emoji_in_header_label(): void
    {
        $language = file_get_contents(__DIR__.'/../../../../../../app/Language/en-US.ini');

        $this->assertIsString($language);
        $this->assertStringContainsString('widgets.title.high_impact_next = "💥 High Impact Next"', $language);
        $this->assertStringContainsString('headlines.high_impact_next = "💥 High Impact Next"', $language);
    }
}
