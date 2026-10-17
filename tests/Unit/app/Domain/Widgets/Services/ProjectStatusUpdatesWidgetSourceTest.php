<?php

namespace Unit\app\Domain\Widgets\Services;

use Unit\TestCase;

class ProjectStatusUpdatesWidgetSourceTest extends TestCase
{
    public function test_widgets_service_registers_project_status_updates_widget(): void
    {
        $service = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Services/Widgets.php');

        $this->assertIsString($service);
        $this->assertStringContainsString("availableWidgets['projectstatusupdates']", $service);
        $this->assertStringContainsString('widgets.title.project_status_updates', $service);
        $this->assertStringContainsString('/widgets/projectStatusUpdates/get', $service);
    }
}
