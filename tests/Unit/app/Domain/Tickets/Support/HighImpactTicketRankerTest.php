<?php

namespace Unit\app\Domain\Tickets\Support;

require_once __DIR__.'/../../../../../../app/Domain/Tickets/Support/HighImpactTicketRanker.php';

use Carbon\CarbonImmutable;
use Leantime\Domain\Tickets\Support\HighImpactTicketRanker;
use Unit\TestCase;

class HighImpactTicketRankerTest extends TestCase
{
    public function test_extract_signals_reads_boolean_and_key_value_tags(): void
    {
        $ranker = new HighImpactTicketRanker();

        $signals = $ranker->extractSignals('focus, expected:true, assessment_flag:1, impact:high, provision_ref:morpro-42, focus_note:core-tracked-launch-blocker');

        $this->assertTrue($signals['focus']);
        $this->assertTrue($signals['expected']);
        $this->assertTrue($signals['assessment']);
        $this->assertSame('high', $signals['impactLabel']);
        $this->assertSame(24, $signals['impactWeight']);
        $this->assertSame('morpro-42', $signals['provisionRef']);
        $this->assertSame('core-tracked-launch-blocker', $signals['focusNote']);
    }

    public function test_rank_prioritizes_focus_expected_and_due_signals(): void
    {
        $ranker = new HighImpactTicketRanker();

        $tickets = [
            [
                'id' => 10,
                'headline' => 'Plain task',
                'priority' => 3,
                'dateToFinish' => '2026-04-05 09:00:00',
                'tags' => '',
            ],
            [
                'id' => 11,
                'headline' => 'High impact task',
                'priority' => 2,
                'dateToFinish' => '2026-03-27 09:00:00',
                'tags' => 'focus, expected, assessment, impact:high, provision_ref:morpro-42',
            ],
        ];

        $ranked = $ranker->rank($tickets, 8, CarbonImmutable::parse('2026-03-26 09:00:00'));

        $this->assertCount(2, $ranked);
        $this->assertSame(11, $ranked[0]['id']);
        $this->assertGreaterThan($ranked[1]['highImpact']['score'], $ranked[0]['highImpact']['score']);
        $this->assertSame('dueSoon', $ranked[0]['highImpact']['dueState']);
    }
}
