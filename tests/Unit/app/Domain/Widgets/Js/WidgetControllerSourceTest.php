<?php

namespace Unit\app\Domain\Widgets\Js;

use Unit\TestCase;

class WidgetControllerSourceTest extends TestCase
{
    public function test_save_grid_uses_grid_nodes_and_dom_elements_for_widget_identity(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Js/Widgetcontroller.js');

        $this->assertIsString($source);
        $this->assertStringContainsString('grid.engine.nodes.map(function(node)', $source);
        $this->assertStringContainsString('jQuery(node.el).find("[hx-get]").first()', $source);
        $this->assertStringContainsString('return typeof item.id !== "undefined" && item.id !== "";', $source);
    }

    public function test_toggle_widget_visibility_uses_computed_available_position(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Js/Widgetcontroller.js');

        $this->assertIsString($source);
        $this->assertStringContainsString('x: position.x,', $source);
        $this->assertStringContainsString('y: position.y,', $source);
    }
}
