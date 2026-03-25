<?php

namespace Unit\app\Domain\Tickets\Templates;

use Unit\TestCase;

class ShowTicketModalTemplateTest extends TestCase
{
    public function test_modal_template_displays_notifications(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showTicketModal.blade.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('$tpl->displayNotification()', $template);
    }

    public function test_modal_template_uses_stable_dependency_schedule_guard_inputs(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showTicketModal.blade.php');
        $detailsTemplate = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/submodules/ticketDetails.sub.php');

        $this->assertIsString($template);
        $this->assertIsString($detailsTemplate);
        $this->assertStringContainsString('datepicker("getDate")', $template);
        $this->assertStringContainsString('finishTimestamp', $template);
        $this->assertStringContainsString('finishTimestamp', $detailsTemplate);
    }

    public function test_modal_template_submits_github_elevation_inside_modal_flow(): void
    {
        $template = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Templates/showTicketModal.blade.php');

        $this->assertIsString($template);
        $this->assertStringContainsString('<form class="formModal" action="<?= BASE_URL ?>/tickets/showTicket/<?php echo $ticket->id ?>" method="post">', $template);
        $this->assertStringContainsString('<input type="hidden" name="elevateGithub" value="1" />', $template);
    }
}
