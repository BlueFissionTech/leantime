<?php

namespace Unit\app\Domain\Tickets\Controllers;

use Unit\TestCase;

class ShowTicketGithubFeedbackTest extends TestCase
{
    public function test_show_ticket_controller_rerenders_modal_after_github_elevation(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Controllers/ShowTicket.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("return \$this->renderTicketModal(\$freshTicket, 'githubstatus');", $controller);
    }

    public function test_ticket_modal_template_sets_active_github_tab_and_displays_notifications(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showTicketModal.blade.php');
        $js = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Js/ticketsController.js');

        $this->assertIsString($template);
        $this->assertIsString($js);
        $this->assertStringContainsString('$tpl->displayNotification()', $template);
        $this->assertStringContainsString('window.leantime.activeTicketTab', $template);
        $this->assertStringContainsString("window.leantime.activeTicketTab || url.searchParams.get(\"tab\")", $js);
    }
}
