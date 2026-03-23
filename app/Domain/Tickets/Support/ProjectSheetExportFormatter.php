<?php

namespace Leantime\Domain\Tickets\Support;

use Carbon\CarbonImmutable;

class ProjectSheetExportFormatter
{
    public function format(array $tickets, ?CarbonImmutable $updatedSince = null): array
    {
        $rows = [];

        foreach ($tickets as $ticket) {
            if ($updatedSince !== null && ! $this->wasUpdatedAfter($ticket['modified'] ?? null, $updatedSince)) {
                continue;
            }

            $rows[] = [
                'todoId' => (int) ($ticket['id'] ?? 0),
                'todoTitle' => (string) ($ticket['headline'] ?? ''),
                'normalizedTodoNumber' => $this->extractNormalizedNumber((string) ($ticket['headline'] ?? '')),
                'milestoneId' => ! empty($ticket['milestoneid']) ? (int) $ticket['milestoneid'] : null,
                'milestoneTitle' => (string) ($ticket['milestoneHeadline'] ?? ''),
                'normalizedMilestoneNumber' => $this->extractNormalizedNumber((string) ($ticket['milestoneHeadline'] ?? '')),
                'statusCode' => (string) ($ticket['status'] ?? ''),
                'statusLabel' => (string) ($ticket['statusLabel'] ?? ''),
                'approval' => $this->extractTagValue((string) ($ticket['tags'] ?? ''), 'approval'),
                'gat' => $this->extractTagValue((string) ($ticket['tags'] ?? ''), 'gat'),
                'modified' => (string) ($ticket['modified'] ?? ''),
                'projectId' => (int) ($ticket['projectId'] ?? 0),
            ];
        }

        return $rows;
    }

    private function wasUpdatedAfter(?string $modified, CarbonImmutable $updatedSince): bool
    {
        if (! is_string($modified) || trim($modified) === '') {
            return false;
        }

        return CarbonImmutable::parse($modified)->greaterThan($updatedSince);
    }

    private function extractNormalizedNumber(string $label): ?string
    {
        if (preg_match('/(?:^|\\b)(\\d+(?:[.\\-]\\d+)*)(?:\\b|\\s*-)/', $label, $matches) === 1) {
            return str_replace('-', '.', $matches[1]);
        }

        return null;
    }

    private function extractTagValue(string $tags, string $field): ?string
    {
        if ($tags === '') {
            return null;
        }

        $pattern = '/(?:^|,|\\|)\\s*'.preg_quote($field, '/').'\\s*[:=]\\s*([^,|]+)/i';

        if (preg_match($pattern, $tags, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
