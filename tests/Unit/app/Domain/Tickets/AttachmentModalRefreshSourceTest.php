<?php

namespace Unit\app\Domain\Tickets;

use PHPUnit\Framework\TestCase;

/**
 * Guard modal wiring without claiming interactive deletion coverage.
 */
final class AttachmentModalRefreshSourceTest extends TestCase
{
    /** @return void */
    public function testDeleteLinkUsesExistingModalHandler(): void
    {
        $root = dirname(__DIR__, 5);
        $template = file_get_contents($root . '/app/Domain/Tickets/Templates/submodules/attachments.sub.php');
        $handler = file_get_contents($root . '/public/assets/js/app/core/modals.js');

        self::assertMatchesRegularExpression('/href="[^"\n]*\?delFile=[^"\n]*" class="delete formModal deleteFile"/', $template);
        self::assertStringContainsString('jQuery(".formModal, .modal").nyroModal(modalOptions)', $handler);
    }

    /** @return void */
    public function testSuccessfulDeleteKeepsTicketFilesContextAndEmitsRefresh(): void
    {
        $source = file_get_contents(dirname(__DIR__, 5) . '/app/Domain/Tickets/Controllers/ShowTicket.php');
        $start = strpos($source, "deleteFile(\$params['delFile'])");
        self::assertNotFalse($start);
        $end = strpos($source, 'return $response;', $start);
        self::assertNotFalse($end);
        $success = substr($source, $start, $end - $start);

        self::assertStringContainsString("Frontcontroller::redirect(BASE_URL.'/tickets/showTicket/'.\$id.'#files')", $success);
        self::assertStringContainsString("\$response->headers->set('HX-Trigger', 'ticketUpdate')", $success);
    }
}
