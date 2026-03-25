<?php

namespace Unit\app\Domain\Tickets\Controllers;

use Unit\TestCase;

class ShowTicketSourceTest extends TestCase
{
    public function test_github_elevation_redirect_keeps_github_tab_active(): void
    {
        $controller = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Controllers/ShowTicket.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("\$tab = '?tab=githubstatus';", $controller);
    }
}
