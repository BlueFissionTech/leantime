<?php

namespace Leantime\Domain\Supportcenter\Services;

use Illuminate\Support\Facades\Http;
use Leantime\Core\Files\FileManager;
use Leantime\Domain\Files\Repositories\Files as FileRepository;
use Leantime\Domain\Setting\Repositories\Setting as SettingRepository;

class GithubElevation
{
    public function __construct(
        private SettingRepository $settingRepository,
        private FileRepository $fileRepository,
        private FileManager $fileManager,
    ) {}

    public function getDefaultGithubTitle(object $ticket): string
    {
        return trim((string) ($ticket->headline ?? ''));
    }

    public function getDefaultGithubSummary(object $ticket): string
    {
        $description = trim((string) ($ticket->description ?? ''));

        if ($description === '') {
            return '';
        }

        return $this->convertHtmlToMarkdown($description);
    }

    public function getTicketGithubIssue(int $ticketId): array|false
    {
        $value = $this->settingRepository->getSetting($this->getSettingKey($ticketId), false);

        if (! is_string($value) || $value === '') {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : false;
    }

    public function getTicketGithubStatus(int $ticketId): array|false
    {
        $issue = $this->getTicketGithubIssue($ticketId);

        if ($issue === false) {
            return false;
        }

        $state = $this->resolveIssueState($ticketId, $issue);

        return [
            'status' => $state === 'closed' ? 'Done' : 'Backlog',
            'isDone' => $state === 'closed',
            'isInProgress' => $state !== 'closed',
        ];
    }

    public function createGithubIssue(int $ticketId, object $ticket, array $payload): array
    {
        $existing = $this->getTicketGithubIssue($ticketId);
        if ($existing !== false) {
            return ['ok' => false, 'message' => 'This ticket is already elevated to GitHub.'];
        }

        $repo = $this->normalizeRepository(trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_REPO')));
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

        if ($title === '') {
            $title = $this->getDefaultGithubTitle($ticket);
        }

        if ($summary === '') {
            $summary = $this->getDefaultGithubSummary($ticket);
        }

        if ($title === '' || $summary === '') {
            return ['ok' => false, 'message' => 'GitHub title and technical summary are required.'];
        }

        $repoCheck = $this->checkRepositoryAccess($baseUrl, $repo, $token);
        if ($repoCheck !== true) {
            return ['ok' => false, 'message' => $repoCheck];
        }

        $body = $this->buildIssueBody($ticketId, $ticket, $summary, $reproduction, $impact);
        $labelList = array_values(array_filter(array_map('trim', explode(',', $labels))));

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withUserAgent('BlueFission-Leantime-Supportcenter')
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->post($baseUrl.'/repos/'.$repo.'/issues', [
                'title' => $title,
                'body' => $body,
                'labels' => $labelList,
            ]);

        if (! $response->successful()) {
            $message = trim((string) ($response->json('message') ?? ''));

            if ($message === '' && $response->status() === 404) {
                $message = 'Repository not found or token cannot access it.';
            }

            return ['ok' => false, 'message' => 'GitHub issue creation failed: '.$response->status().($message !== '' ? ' - '.$message : '')];
        }

        $issue = $response->json();

        $meta = [
            'repository' => $repo,
            'number' => (int) ($issue['number'] ?? 0),
            'url' => (string) ($issue['html_url'] ?? ''),
            'apiUrl' => (string) ($issue['url'] ?? ''),
            'state' => (string) ($issue['state'] ?? 'open'),
            'createdAt' => date('Y-m-d H:i:s'),
            'createdBy' => (int) session('userdata.id'),
        ];

        $this->settingRepository->saveSetting($this->getSettingKey($ticketId), json_encode($meta));

        return ['ok' => true, 'message' => 'Elevated to GitHub issue #'.$meta['number'].'.', 'issue' => $meta];
    }

    private function checkRepositoryAccess(string $baseUrl, string $repo, string $token): bool|string
    {
        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withUserAgent('BlueFission-Leantime-Supportcenter')
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get($baseUrl.'/repos/'.$repo);

        if ($response->successful()) {
            return true;
        }

        $requestId = trim((string) $response->header('X-GitHub-Request-Id', ''));

        if ($response->status() === 404) {
            return 'GitHub repository '.$repo.' is not visible to the configured token. Confirm repo name, repo access, and org authorization'.($requestId !== '' ? ' (request '.$requestId.')' : '.');
        }

        $message = trim((string) ($response->json('message') ?? ''));

        return 'GitHub repository preflight failed: '.$response->status().($message !== '' ? ' - '.$message : '').($requestId !== '' ? ' (request '.$requestId.')' : '');
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

    private function convertHtmlToMarkdown(string $html): string
    {
        $html = $this->normalizeLocalFilesGetUrls($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $matches) {
            $tag = $matches[0];
            $src = $this->extractAttribute($tag, 'src');

            if ($src === '') {
                return '';
            }

            $resolvedSrc = $this->resolveDirectFileUrl($src);
            $alt = $this->extractAttribute($tag, 'alt');
            $label = $alt !== '' ? $alt : 'Image';

            if ($resolvedSrc === false) {
                return "\n[$label]($src)\n";
            }

            return "\n![$label]($resolvedSrc)\n";
        }, $html) ?? $html;

        $html = preg_replace_callback('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function (array $matches) {
            $url = trim($matches[1]);
            $resolvedUrl = $this->resolveDirectFileUrl($url);
            $text = trim($this->convertHtmlToPlainText($matches[2]));

            if ($url === '') {
                return $text;
            }

            $finalUrl = $resolvedUrl !== false ? $resolvedUrl : $url;

            if ($text === '' || $text === $finalUrl) {
                return $finalUrl;
            }

            return "[$text]($finalUrl)";
        }, $html) ?? $html;

        $html = preg_replace('/<li\b[^>]*>/i', "\n- ", $html) ?? $html;
        $html = preg_replace('/<(br\s*\/?|\/p|\/div|\/li|\/ul|\/ol|\/h[1-6]|\/tr)>/i', "\n", $html) ?? $html;

        $markdown = strip_tags($html);
        $markdown = preg_replace("/\r\n?/", "\n", $markdown) ?? $markdown;
        $markdown = preg_replace("/[ \t]+\n/", "\n", $markdown) ?? $markdown;
        $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown) ?? $markdown;

        return trim($markdown);
    }

    private function convertHtmlToPlainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\s+/", ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractAttribute(string $tag, string $attribute): string
    {
        if (! preg_match('/\b'.preg_quote($attribute, '/').'\s*=\s*["\']([^"\']*)["\']/i', $tag, $matches)) {
            return '';
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function normalizeLocalFilesGetUrls(string $content): string
    {
        $baseUrl = rtrim((string) BASE_URL, '/');

        $normalized = preg_replace_callback(
            '/\b(?P<attr>href|src)\s*=\s*(?P<quote>[\'"])(?P<url>[^\'"]+)(?P=quote)/i',
            function (array $matches) use ($baseUrl) {
                $decodedUrl = html_entity_decode($matches['url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if (! str_contains($decodedUrl, '/files/get?')) {
                    return $matches[0];
                }

                $parts = parse_url($decodedUrl);

                if ($parts === false) {
                    return $matches[0];
                }

                $path = $parts['path'] ?? '';
                if (! in_array($path, ['/files/get', 'files/get'], true)) {
                    return $matches[0];
                }

                parse_str($parts['query'] ?? '', $queryParams);
                if (
                    ! isset($queryParams['encName'])
                    || ! isset($queryParams['ext'])
                    || ! isset($queryParams['realName'])
                ) {
                    return $matches[0];
                }

                $rewrittenUrl = $baseUrl.'/files/get';
                $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
                if ($queryString !== '') {
                    $rewrittenUrl .= '?'.$queryString;
                }

                if (isset($parts['fragment']) && $parts['fragment'] !== '') {
                    $rewrittenUrl .= '#'.$parts['fragment'];
                }

                return $matches['attr']
                    .'='
                    .$matches['quote']
                    .htmlspecialchars($rewrittenUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    .$matches['quote'];
            },
            $content
        );

        return $normalized ?? $content;
    }

    private function resolveDirectFileUrl(string $url): string|false
    {
        $decodedUrl = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($decodedUrl === '' || ! str_contains($decodedUrl, '/files/get?')) {
            return $this->normalizeAbsoluteUrl($decodedUrl);
        }

        $parts = parse_url($decodedUrl);

        if ($parts === false) {
            return false;
        }

        $path = $parts['path'] ?? '';
        if (! in_array($path, ['/files/get', 'files/get'], true)) {
            return $this->normalizeAbsoluteUrl($decodedUrl);
        }

        parse_str($parts['query'] ?? '', $queryParams);
        $encName = trim((string) ($queryParams['encName'] ?? ''));
        $extension = trim((string) ($queryParams['ext'] ?? ''));

        if ($encName === '' || $extension === '') {
            return false;
        }

        $file = $this->fileRepository->findFileByEncodedName($encName, $extension);

        if ($file === false) {
            return false;
        }

        $fileUrl = $this->fileManager->getFileUrl($encName.'.'.$extension);

        if (! is_string($fileUrl) || $fileUrl === '') {
            return false;
        }

        return $this->normalizeAbsoluteUrl($fileUrl);
    }

    private function normalizeAbsoluteUrl(string $url): string|false
    {
        if ($url === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim((string) BASE_URL, '/').$url;
        }

        return rtrim((string) BASE_URL, '/').'/'.$url;
    }

    private function getSettingKey(int $ticketId): string
    {
        return 'supportcenter.ticket.'.$ticketId.'.githubIssue';
    }

    private function resolveIssueState(int $ticketId, array $issue): string
    {
        $repo = $this->normalizeRepository(trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_REPO')));
        $token = trim((string) $this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_TOKEN'));
        $baseUrl = rtrim((string) ($this->getEnvironmentValue('LEAN_SUPPORT_GITHUB_BASE_URL') ?: 'https://api.github.com'), '/');
        $apiUrl = trim((string) ($issue['apiUrl'] ?? ''));

        if ($repo === '' || $token === '') {
            return strtolower((string) ($issue['state'] ?? 'open'));
        }

        if ($apiUrl === '' && ! empty($issue['number'])) {
            $apiUrl = $baseUrl.'/repos/'.$repo.'/issues/'.(int) $issue['number'];
        }

        if ($apiUrl === '') {
            return strtolower((string) ($issue['state'] ?? 'open'));
        }

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->withUserAgent('BlueFission-Leantime-Supportcenter')
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get($apiUrl);

        if (! $response->successful()) {
            return strtolower((string) ($issue['state'] ?? 'open'));
        }

        $state = strtolower((string) ($response->json('state') ?? 'open'));

        if ($state !== strtolower((string) ($issue['state'] ?? ''))) {
            $issue['state'] = $state;
            $this->settingRepository->saveSetting($this->getSettingKey($ticketId), json_encode($issue));
        }

        return $state;
    }

    private function normalizeRepository(string $repository): string
    {
        if ($repository === '') {
            return '';
        }

        $repository = preg_replace('#^https?://github\.com/#i', '', $repository) ?? $repository;
        $repository = preg_replace('#^github\.com/#i', '', $repository) ?? $repository;
        $repository = preg_replace('#\.git$#i', '', $repository) ?? $repository;

        return trim($repository, '/');
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
