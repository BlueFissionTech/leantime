<?php

namespace Leantime\Domain\Supportportal\Services;

use Illuminate\Support\Str;
use Leantime\Core\Configuration\Environment;
use Leantime\Domain\Clients\Repositories\Clients as ClientRepository;
use Leantime\Domain\Projects\Repositories\Projects as ProjectRepository;
use Leantime\Domain\Setting\Services\Setting as SettingService;

class PortalResolver
{
    public function __construct(
        private SettingService $settingService,
        private ClientRepository $clientRepository,
        private ProjectRepository $projectRepository,
        private Environment $config,
    ) {}

    public function resolveCurrentHost(string $host): array|false
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        try {
            $configuredPortal = $this->resolveConfiguredPortal($host);
            if ($configuredPortal !== false) {
                return $configuredPortal;
            }

            return $this->resolveSeedPortal($host);
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveConfiguredPortal(string $host): array|false
    {
        $envPortal = $this->decodePortalConfig($this->getEnvironmentValue('LEAN_SUPPORT_PORTAL_'.strtoupper(str_replace(['.', '-'], '_', $host))));
        if ($envPortal !== false) {
            return $this->normalizePortal($envPortal, $host);
        }

        $envPortalMap = $this->decodePortalConfig($this->getEnvironmentValue('LEAN_SUPPORT_PORTALS'));
        if (is_array($envPortalMap) && isset($envPortalMap[$host]) && is_array($envPortalMap[$host])) {
            return $this->normalizePortal($envPortalMap[$host], $host);
        }

        $directKey = 'supportportal.host.'.str_replace('.', '_', $host);
        $directPortal = $this->decodePortalConfig($this->settingService->getSetting($directKey, false));
        if ($directPortal !== false) {
            return $this->normalizePortal($directPortal, $host);
        }

        $hostMap = $this->decodePortalConfig($this->settingService->getSetting('supportportal.hosts', false));

        if (is_array($hostMap) && isset($hostMap[$host]) && is_array($hostMap[$host])) {
            return $this->normalizePortal($hostMap[$host], $host);
        }

        return false;
    }

    private function resolveSeedPortal(string $host): array|false
    {
        $baseHost = preg_replace('/^support\./', '', $host);
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1'], true);
        $isSeedHost = str_contains($host, 'support') || $isLocalHost;

        if (! $isSeedHost) {
            return false;
        }

        $client = $this->findClientByNeedles($this->buildHostNeedles($baseHost !== '' ? $baseHost : 'support'));
        if ($client === false) {
            $client = $isLocalHost ? $this->findFirstClientWithSupportProject() : false;
        }

        if ($client === false) {
            return false;
        }

        $project = $this->resolveSupportProject((int) $client['id']);
        if ($project === false) {
            return false;
        }

        return $this->normalizePortal([
            'slug' => 'client-support-portal',
            'name' => 'Client Support',
            'host' => $host,
            'clientId' => (int) $client['id'],
            'projectId' => (int) $project['id'],
            'projectName' => $project['name'],
            'productName' => 'Software Support',
            'allowSelfSignup' => true,
            'defaultTags' => 'support,software',
            'brandName' => $client['name'] ?? 'Client Support',
            'brandLogo' => '',
            'primaryColor' => '#173B6D',
            'secondaryColor' => '#28A7A1',
        ], $host);
    }

    private function normalizePortal(array $portal, string $host): array
    {
        $projectId = isset($portal['projectId']) ? (int) $portal['projectId'] : 0;
        $clientId = isset($portal['clientId']) ? (int) $portal['clientId'] : 0;

        if ($projectId === 0 && $clientId > 0) {
            $project = $this->resolveSupportProject($clientId);
            $projectId = $project['id'] ?? 0;
            $portal['projectName'] = $portal['projectName'] ?? ($project['name'] ?? '');
        }

        return [
            'slug' => $portal['slug'] ?? Str::slug($portal['name'] ?? $host),
            'name' => $portal['name'] ?? ($portal['brandName'] ?? 'Support Portal'),
            'host' => $portal['host'] ?? $host,
            'clientId' => $clientId,
            'projectId' => $projectId,
            'projectName' => $portal['projectName'] ?? '',
            'productName' => $portal['productName'] ?? ($portal['name'] ?? 'Support'),
            'allowSelfSignup' => filter_var($portal['allowSelfSignup'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'defaultTags' => $portal['defaultTags'] ?? 'support',
            'brandName' => $portal['brandName'] ?? ($portal['name'] ?? 'Support'),
            'brandLogo' => $portal['brandLogo'] ?? '',
            'primaryColor' => $portal['primaryColor'] ?? '#173B6D',
            'secondaryColor' => $portal['secondaryColor'] ?? '#28A7A1',
        ];
    }

    private function decodePortalConfig(mixed $value): array|false
    {
        if ($value === false || $value === null || $value === '') {
            return false;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return false;
        }

        $decoded = json_decode(trim($value), true);

        if (! is_array($decoded)) {
            $trimmed = trim($value);

            if (
                strlen($trimmed) >= 2
                && (($trimmed[0] === '"' && $trimmed[strlen($trimmed) - 1] === '"')
                || ($trimmed[0] === "'" && $trimmed[strlen($trimmed) - 1] === "'"))
            ) {
                $decoded = json_decode(stripslashes(substr($trimmed, 1, -1)), true);
            }
        }

        return is_array($decoded) ? $decoded : false;
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

        $configValue = $this->config->get($key);
        if ($configValue !== null && $configValue !== '') {
            return $configValue;
        }

        return false;
    }

    private function findClientByNeedle(string $needle): array|false
    {
        return $this->findClientByNeedles([$needle]);
    }

    private function findClientByNeedles(array $needles): array|false
    {
        $needles = array_values(array_unique(array_filter(array_map(function ($needle) {
            return $this->normalizeNeedle((string) $needle);
        }, $needles))));

        if (count($needles) === 0) {
            return false;
        }

        foreach ($this->clientRepository->getAll() as $client) {
            $internet = $this->normalizeNeedle((string) ($client['internet'] ?? ''));
            $name = $this->normalizeNeedle((string) ($client['name'] ?? ''));

            foreach ($needles as $needle) {
                if (
                    ($internet !== '' && (str_contains($internet, $needle) || str_contains($needle, $internet)))
                    || ($name !== '' && (str_contains($name, $needle) || str_contains($needle, $name)))
                ) {
                    return $client;
                }
            }
        }

        return false;
    }

    private function buildHostNeedles(string $host): array
    {
        $host = strtolower(trim($host));
        $labels = preg_split('/[.\-]+/', $host) ?: [];
        $needles = [$host];

        foreach ($labels as $label) {
            if ($label === '' || in_array($label, ['com', 'net', 'org', 'io', 'app', 'co'], true)) {
                continue;
            }

            $needles[] = $label;

            foreach (['support', 'portal', 'client', 'next', 'app'] as $suffix) {
                if (str_ends_with($label, $suffix)) {
                    $trimmed = substr($label, 0, -strlen($suffix));
                    if (strlen($trimmed) >= 4) {
                        $needles[] = $trimmed;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($needles)));
    }

    private function normalizeNeedle(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', $value));
    }

    private function findFirstClientWithSupportProject(): array|false
    {
        foreach ($this->clientRepository->getAll() as $client) {
            if ($this->resolveSupportProject((int) $client['id']) !== false) {
                return $client;
            }
        }

        return false;
    }

    private function resolveSupportProject(int $clientId): array|false
    {
        $projects = $this->projectRepository->getClientProjects($clientId);

        if (! is_array($projects) || count($projects) === 0) {
            return false;
        }

        usort($projects, function (array $left, array $right) {
            return $this->projectRank($left) <=> $this->projectRank($right);
        });

        $candidate = $projects[0] ?? false;

        return $candidate !== false && $this->projectRank($candidate) < 999 ? $candidate : false;
    }

    private function projectRank(array $project): int
    {
        $name = strtolower((string) ($project['name'] ?? ''));

        if ($name === '') {
            return 999;
        }

        if (str_contains($name, 'support')) {
            return 1;
        }

        if (str_contains($name, 'help')) {
            return 2;
        }

        if (str_contains($name, 'service')) {
            return 3;
        }

        return 999;
    }
}
