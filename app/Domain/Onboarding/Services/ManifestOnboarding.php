<?php

namespace Leantime\Domain\Onboarding\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class ManifestOnboarding
{
    private const DEFAULT_TIMEOUT = 20;

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '';
    }

    public function supportsWrite(): bool
    {
        return filter_var(env('LEAN_MANIFEST_WRITE_ENABLED', false), FILTER_VALIDATE_BOOL);
    }

    public function defaultTemplateKey(): string
    {
        return trim((string) env('LEAN_MANIFEST_DEFAULT_TEMPLATE_KEY', 'eidolon_intake'));
    }

    public function templateExport(string $templateKey): array
    {
        return $this->getJson($this->path('template_export', '/api/questionnaire/template-export'), [
            'template-key' => $templateKey,
        ]);
    }

    public function sessionRead(string $templateKey, string $externalRef): array
    {
        return $this->getJson($this->path('session_read', '/api/questionnaire/session-read'), [
            'template-key' => $templateKey,
            'external-ref' => $externalRef,
        ], true);
    }

    public function draftRead(string $subjectType, ?string $subjectKey, ?string $subjectName): array
    {
        $query = ['subject-type' => $subjectType];
        if ($subjectKey !== null && $subjectKey !== '') {
            $query['subject-key'] = $subjectKey;
        }
        if ($subjectName !== null && $subjectName !== '') {
            $query['subject-name'] = $subjectName;
        }

        return $this->getJson($this->path('draft_read', '/api/questionnaire/draft-read'), $query, true);
    }

    public function syncPreview(string $subjectType, ?string $subjectKey, ?string $subjectName, ?int $projectId): array
    {
        $query = ['subject-type' => $subjectType];
        if ($subjectKey !== null && $subjectKey !== '') {
            $query['subject-key'] = $subjectKey;
        }
        if ($subjectName !== null && $subjectName !== '') {
            $query['subject-name'] = $subjectName;
        }
        if ($projectId !== null && $projectId > 0) {
            $query['project-id'] = $projectId;
        }

        return $this->getJson($this->path('sync_preview', '/api/questionnaire/sync-preview'), $query, true);
    }

    public function sessionUpsert(array $payload): array
    {
        return $this->postJson($this->path('session_upsert', '/api/questionnaire/session-upsert'), $payload);
    }

    public function syncApply(array $payload): array
    {
        return $this->postJson($this->path('sync_apply', '/api/questionnaire/sync-apply'), $payload);
    }

    private function getJson(string $path, array $query = [], bool $allowNotFound = false): array
    {
        if (! $this->isConfigured()) {
            return $this->failure('Manifest onboarding is not configured.', 500);
        }

        $response = $this->client()->get($this->url($path), array_filter($query, static fn ($value) => $value !== null && $value !== ''));

        return $this->normalize($response->status(), $response->json(), $allowNotFound);
    }

    private function postJson(string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            return $this->failure('Manifest onboarding is not configured.', 500);
        }

        if (! $this->supportsWrite()) {
            return $this->failure('Manifest write transport is not enabled for this environment.', 501);
        }

        $response = $this->client()->post($this->url($path), $payload);

        return $this->normalize($response->status(), $response->json(), false);
    }

    private function normalize(int $status, mixed $json, bool $allowNotFound): array
    {
        if (is_array($json)) {
            if ($allowNotFound && $status === 404) {
                return ['ok' => true, 'status' => $status, 'data' => []];
            }

            if ($status >= 200 && $status < 300 && ! isset($json['error'])) {
                return ['ok' => true, 'status' => $status, 'data' => $json];
            }

            $error = Arr::get($json, 'error') ?: Arr::get($json, 'message') ?: 'Manifest onboarding request failed.';

            return $this->failure((string) $error, $status, $json);
        }

        if ($allowNotFound && $status === 404) {
            return ['ok' => true, 'status' => $status, 'data' => []];
        }

        return $this->failure('Manifest onboarding returned an invalid response.', $status);
    }

    private function failure(string $message, int $status, array $data = []): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $message,
            'data' => $data,
        ];
    }

    private function client(): PendingRequest
    {
        $request = Http::withoutVerifying()
            ->acceptJson()
            ->asJson()
            ->timeout((int) env('LEAN_MANIFEST_TIMEOUT', self::DEFAULT_TIMEOUT));

        $apiKey = trim((string) env('LEAN_MANIFEST_API_KEY', ''));
        if ($apiKey !== '') {
            $request = $request->withHeaders(['x-api-key' => $apiKey]);
        }

        return $request;
    }

    private function baseUrl(): string
    {
        return rtrim(trim((string) env('LEAN_MANIFEST_BASE_URL', '')), '/');
    }

    private function path(string $envSuffix, string $default): string
    {
        $key = 'LEAN_MANIFEST_'.strtoupper($envSuffix).'_PATH';

        return trim((string) env($key, $default));
    }

    private function url(string $path): string
    {
        return $this->baseUrl().'/'.ltrim($path, '/');
    }
}
