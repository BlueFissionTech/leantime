<?php

namespace Unit\app\Domain\Tickets;

use PHPUnit\Framework\TestCase;

/**
 * Guard scoped responsive hooks without claiming browser layout coverage.
 */
final class TicketModalMobileSourceTest extends TestCase
{
    /** @return void */
    public function testModalRetainsDesktopSizingAndProvidesScopedMobileHooks(): void
    {
        $root = dirname(__DIR__, 5);
        $modal = file_get_contents($root . '/app/Domain/Tickets/Templates/showTicketModal.blade.php');
        $details = file_get_contents($root . '/app/Domain/Tickets/Templates/submodules/ticketDetails.sub.php');

        self::assertStringContainsString('class="ticket-modal-shell"', $modal);
        self::assertStringContainsString('min-width:70%', $modal);
        self::assertStringContainsString('row ticket-details-layout', $details);
        self::assertSame(7, substr_count($details, 'form-group ticket-meta-row'));
    }

    /** @return void */
    public function testMobileRulesStackFieldsAndKeepControlsWithinTheModal(): void
    {
        $css = file_get_contents(dirname(__DIR__, 5) . '/public/assets/css/components/mobile.css');

        self::assertMatchesRegularExpression('/\.nyroModalCont \.ticket-modal-shell\s*\{[^}]*min-width:\s*0 !important;/s', $css);
        self::assertMatchesRegularExpression('/\.nyroModalCont \.ticket-meta-row\s*\{[^}]*display:\s*block !important;[^}]*width:\s*100% !important;/s', $css);
        self::assertStringContainsString('.nyroModalCont .ticket-details-layout > [class*="col-md-"]', $css);
        self::assertStringContainsString('.nyroModalCont .ticket-modal-shell .chosen-container', $css);
        self::assertStringContainsString('.nyroModalCont .ticket-modal-shell .sticky-modal-footer input[type="submit"]', $css);
    }
}
