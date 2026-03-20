<?php

namespace Leantime\Domain\Supportcenter\Services;

use Illuminate\Support\Facades\Http;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;

class GithubElevation
{
    public function __construct(
        private SettingRepository $settingRepository,
    ) {}

    public function getTicketGithubIssue(int $ticketId): array|false
    {
        $value = $this->settingRepository->getSetting($this->getSettingKey($ticketId), false);

        if (! is_string($value) || $value === '') {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : false;
    }

    public function createGithubIssue(int $ticketId, object $ticket, array $payload): array
    {
        $existing = $this->getTicketGithubIssue($ticketId);
        if ($existing !== false) {
            return ['ok' => false, 'message' => 'This ticket is already elevated to GitHub.'];
        }

        $repo = trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_REPO'));
        $token = trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_TOKEN'));
        $labels = trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_LABELS'));
        $baseUrl = rtrim((string) ($this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_BASE_URL') ?: 'https://api.github.com'), '/');

        if ($repo === '' || $token === '') {
            return ['ok' => false, 'message' => 'GitHub elevation is not configured yet.'];
        }

        $title = trim((string) ($payload['githubTitle'] ?? ''));
        $summary = trim((string) ($payload['githubSummary'] ?? ''));
        $reproduction = trim((string) ($payload['githubReproduction'] ?? ''));
        $impact = trim((string) ($payload['githubImpact'] ?? ''));

        if ($title === '' || $summary === '') {
            return ['ok' => false, 'message' => 'GitHub title and technical summary are required.'];
        }

        $body = $this->buildIssueBody($ticketId, $ticket, $summary, $reproduction, $impact);
        $labelList = array_values(array_filter(array_map('trim', explode(',', $labels))));

        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post($baseUrl.'/repos/'.$repo.'/issues', [
                'title' => $title,
                'body' => $body,
                'labels' => $labelList,
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'GitHub issue creation failed: '.$response->status()];
        }

        $issue = $response->json();

        $meta = [
            'repository' => $repo,
            'number' => (int) ($issue['number'] ?? 0),
            'url' => (string) ($issue['html_url'] ?? ''),
            'apiUrl' => (string) ($issue['url'] ?? ''),
            'state' => 'elevated',
            'createdAt' => date('Y-m-d H:i:s'),
            'createdBy' => (int) session('userdata.id'),
        ];

        $this->settingRepository->saveSetting($this->getSettingKey($ticketId), json_encode($meta));

        return ['ok' => true, 'message' => 'Elevated to GitHub issue #'.$meta['number'].'.', 'issue' => $meta];
    }

    private function buildIssueBody(int $ticketId, object $ticket, string $summary, string $reproduction, string $impact): string
    {
        $parts = [
            "Support ticket reference: #{$ticketId}",
            '',
            'Technical summary:',
            $summary,
        ];

        if ($reproduction !== '') {
            $parts[] = '';
            $parts[] = 'Reproduction notes:';
            $parts[] = $reproduction;
        }

        if ($impact !== '') {
            $parts[] = '';
            $parts[] = 'Impact:';
            $parts[] = $impact;
        }

        $parts[] = '';
        $parts[] = 'Original ticket headline:';
        $parts[] = (string) ($ticket->headline ?? '');

        return implode("\n", $parts);
    }

    private function getSettingKey(int $ticketId): string
    {
        return 'supportcenter.ticket.'.$ticketId.'.githubIssue';
    }

    private function getEnvironmentValue(string $key): mixed
    {
        $value = getenv($key);

        if ($value !== false && $value !== null && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return false;
    }
}
