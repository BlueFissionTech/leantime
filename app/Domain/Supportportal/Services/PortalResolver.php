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

        $configuredPortal = $this->resolveConfiguredPortal($host);
        if ($configuredPortal !== false) {
            return $configuredPortal;
        }

        return $this->resolveSeedPortal($host);
    }

    private function resolveConfiguredPortal(string $host): array|false
    {
        $envPortal = $this->decodePortalConfig(env('LEAN_SUPPORT_PORTAL_'.strtoupper(str_replace(['.', '-'], '_', $host))));
        if ($envPortal !== false) {
            return $this->normalizePortal($envPortal, $host);
        }

        $envPortalMap = $this->decodePortalConfig(env('LEAN_SUPPORT_PORTALS'));
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

        $client = $this->findClientByNeedle($baseHost !== '' ? $baseHost : 'support');
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

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : false;
    }

    private function findClientByNeedle(string $needle): array|false
    {
        $needle = strtolower($needle);

        foreach ($this->clientRepository->getAll() as $client) {
            $internet = strtolower((string) ($client['internet'] ?? ''));
            $name = strtolower((string) ($client['name'] ?? ''));

            if (($internet !== '' && str_contains($internet, $needle)) || ($name !== '' && str_contains($name, $needle))) {
                return $client;
            }
        }

        return false;
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
