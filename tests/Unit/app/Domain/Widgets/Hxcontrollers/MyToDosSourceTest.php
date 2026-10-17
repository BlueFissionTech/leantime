<?php

namespace Unit\app\Domain\Widgets\Hxcontrollers;

use Unit\TestCase;

class MyToDosSourceTest extends TestCase
{
    public function test_update_title_triggers_ticket_update_event_for_dashboard_widgets(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Widgets/Hxcontrollers/MyToDos.php');

        $this->assertIsString($source);
        $this->assertStringContainsString("use Leantime\\Domain\\Tickets\\Htmx\\HtmxTicketEvents;", $source);
        $this->assertStringContainsString("\$this->tpl->setHTMXEvent(HtmxTicketEvents::UPDATE->value);", $source);
    }
}
