<?php

namespace Unit\app\Domain\Widgets\Services;

use Unit\TestCase;

class WidgetsSourceTest extends TestCase
{
    public function test_widgets_service_registers_high_impact_next_widget(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Services/Widgets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("'id' => 'highimpactnext'", $source);
        $this->assertStringContainsString("'widgetUrl' => BASE_URL.'/widgets/highImpactNext/get'", $source);
        $this->assertStringContainsString("'name' => 'widgets.title.high_impact_next'", $source);
    }
}
