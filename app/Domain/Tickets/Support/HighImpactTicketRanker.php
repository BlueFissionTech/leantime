<?php

namespace Leantime\Domain\Tickets\Support;

use Carbon\CarbonImmutable;

class HighImpactTicketRanker
{
    private const INVALID_DUE_DATES = [
        '',
        '0000-00-00 00:00:00',
        '1969-12-31 00:00:00',
        '0000-00-00',
        '1969-12-31',
    ];

    public function rank(array $tickets, int $limit = 8, ?CarbonImmutable $now = null): array
    {
        $now = $now ?? CarbonImmutable::now();

        $ranked = array_map(function (array $ticket) use ($now) {
            $signals = $this->extractSignals($ticket['tags'] ?? '');
            $dueState = $this->resolveDueState($ticket['dateToFinish'] ?? null, $now);
            $score = $this->score($ticket, $signals, $dueState);

            $ticket['highImpact'] = [
                'score' => $score,
                'focus' => $signals['focus'],
                'expected' => $signals['expected'],
                'impactLabel' => $signals['impactLabel'],
                'impactWeight' => $signals['impactWeight'],
                'provisionRef' => $signals['provisionRef'],
                'dueState' => $dueState,
            ];

            return $ticket;
        }, $tickets);

        usort($ranked, function (array $left, array $right) {
            $scoreCompare = ($right['highImpact']['score'] ?? 0) <=> ($left['highImpact']['score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $leftDue = $this->sortableDueDate($left['dateToFinish'] ?? null);
            $rightDue = $this->sortableDueDate($right['dateToFinish'] ?? null);
            $dueCompare = $leftDue <=> $rightDue;
            if ($dueCompare !== 0) {
                return $dueCompare;
            }

            $priorityCompare = ((int) ($left['priority'] ?? 999)) <=> ((int) ($right['priority'] ?? 999));
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return array_slice($ranked, 0, max(1, $limit));
    }

    public function extractSignals(?string $tags): array
    {
        $signals = [
            'focus' => false,
            'expected' => false,
            'impactLabel' => null,
            'impactWeight' => 0,
            'provisionRef' => null,
        ];

        foreach ($this->tokenizeTags($tags) as $token) {
            $lowerToken = strtolower($token);
            [$key, $value] = $this->splitTagToken($lowerToken);

            if (in_array($key, ['focus', 'focus_flag'], true)) {
                $signals['focus'] = $this->isTruthyTagValue($value);
                continue;
            }

            if (in_array($key, ['expected', 'expected_flag'], true)) {
                $signals['expected'] = $this->isTruthyTagValue($value);
                continue;
            }

            if (in_array($key, ['impact', 'impact_tier'], true) && $value !== null && $value !== '') {
                $signals['impactLabel'] = $value;
                $signals['impactWeight'] = $this->resolveImpactWeight($value);
                continue;
            }

            if (in_array($key, ['provision', 'provision_ref', 'provisioned'], true)) {
                $signals['provisionRef'] = $value === null || $value === '' ? 'provisioned' : $value;
                continue;
            }

            if ($lowerToken === 'focus') {
                $signals['focus'] = true;
            } elseif ($lowerToken === 'expected') {
                $signals['expected'] = true;
            } elseif ($lowerToken === 'provisioned') {
                $signals['provisionRef'] = 'provisioned';
            }
        }

        return $signals;
    }

    private function score(array $ticket, array $signals, ?string $dueState): int
    {
        $score = 0;

        if ($signals['focus']) {
            $score += 35;
        }

        if ($signals['expected']) {
            $score += 24;
        }

        if ($signals['provisionRef'] !== null) {
            $score += 14;
        }

        $score += $signals['impactWeight'];

        if ($dueState === 'overdue') {
            $score += 18;
        } elseif ($dueState === 'dueSoon') {
            $score += 10;
        }

        $priority = (int) ($ticket['priority'] ?? 0);
        $score += match ($priority) {
            1 => 12,
            2 => 9,
            3 => 6,
            4 => 3,
            default => 1,
        };

        return $score;
    }

    private function resolveDueState(?string $dateToFinish, CarbonImmutable $now): ?string
    {
        if ($dateToFinish === null || in_array($dateToFinish, self::INVALID_DUE_DATES, true)) {
            return null;
        }

        try {
            $dueDate = CarbonImmutable::parse($dateToFinish);
        } catch (\Throwable) {
            return null;
        }

        $today = $now->startOfDay();

        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        if ($dueDate->lte($today->addDays(3)->endOfDay())) {
            return 'dueSoon';
        }

        return null;
    }

    private function sortableDueDate(?string $dateToFinish): int
    {
        if ($dateToFinish === null || in_array($dateToFinish, self::INVALID_DUE_DATES, true)) {
            return PHP_INT_MAX;
        }

        try {
            return CarbonImmutable::parse($dateToFinish)->getTimestamp();
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    private function tokenizeTags(?string $tags): array
    {
        if ($tags === null || trim($tags) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags))));
    }

    private function splitTagToken(string $token): array
    {
        if (str_contains($token, ':')) {
            return array_pad(explode(':', $token, 2), 2, null);
        }

        if (str_contains($token, '=')) {
            return array_pad(explode('=', $token, 2), 2, null);
        }

        return [$token, null];
    }

    private function isTruthyTagValue(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        return ! in_array($value, ['0', 'false', 'no', 'off'], true);
    }

    private function resolveImpactWeight(string $value): int
    {
        if (is_numeric($value)) {
            return max(0, min(5, (int) $value)) * 6;
        }

        return match ($value) {
            'critical' => 30,
            'high' => 24,
            'medium' => 16,
            'low' => 8,
            default => 0,
        };
    }
}
