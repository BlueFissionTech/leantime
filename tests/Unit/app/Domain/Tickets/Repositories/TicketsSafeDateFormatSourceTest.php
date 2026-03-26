<?php

namespace Unit\app\Domain\Tickets\Repositories;

use Unit\TestCase;

class TicketsSafeDateFormatSourceTest extends TestCase
{
    public function test_ticket_repository_guards_empty_datetime_values_before_formatting(): void
    {
        $source = file_get_contents(__DIR__.'/../../../../../../app/Domain/Tickets/Repositories/Tickets.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('private function getSafeDateFormatExpression(string $column, string $format): string', $source);
        $this->assertStringContainsString('{$column} = \'\'', $source);
        $this->assertStringContainsString('{$column} = \'0000-00-00 00:00:00\'', $source);
        $this->assertStringContainsString('{$column} = \'1969-12-31 00:00:00\'', $source);
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($source, 'getSafeDateFormatExpression('),
            'Expected repository timeline selects to use safe date formatting.'
        );
    }
}
