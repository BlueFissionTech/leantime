<?php

namespace Leantime\Domain\Onboarding\Controllers;

use Leantime\Core\Controller\Controller;
use Leantime\Core\Controller\Frontcontroller;
use Leantime\Domain\Clients\Services\Clients as ClientService;
use Leantime\Domain\Onboarding\Services\ManifestOnboarding;
use Leantime\Domain\Projects\Services\Projects as ProjectService;
use Symfony\Component\HttpFoundation\Response;

class Project extends Controller
{
    private ManifestOnboarding $manifestOnboarding;

    private ProjectService $projectService;

    private ClientService $clientService;

    public function init(
        ManifestOnboarding $manifestOnboarding,
        ProjectService $projectService,
        ClientService $clientService,
    ): void {
        $this->manifestOnboarding = $manifestOnboarding;
        $this->projectService = $projectService;
        $this->clientService = $clientService;
    }

    public function project(array $params): Response
    {
        $projectId = (int) ($params['projectId'] ?? session('currentProject') ?? 0);
        if ($projectId < 1) {
            $this->tpl->setNotification('Select a project before opening onboarding.', 'error');

            return Frontcontroller::redirect(BASE_URL.'/projects/showMy');
        }

        $project = $this->projectService->getProject($projectId);
        if (! is_array($project)) {
            return $this->tpl->display('errors.error404', responseCode: 404);
        }

        if ((int) session('currentProject') !== $projectId) {
            $this->projectService->changeCurrentSessionProject($projectId);
        }

        $subjectType = $this->resolveSubjectType($params);
        $subject = $this->resolveSubject($subjectType, $project);
        $templateKey = trim((string) ($params['templateKey'] ?? $this->manifestOnboarding->defaultTemplateKey()));
        $externalRef = $this->buildExternalRef($subjectType, $subject);

        if ($this->incomingRequest->isMethod('POST')) {
            $post = $this->incomingRequest->all();
            $action = (string) ($post['action'] ?? '');

            if ($action === 'saveSession') {
                $result = $this->manifestOnboarding->sessionUpsert(
                    $this->buildSessionPayload($templateKey, $subjectType, $subject, $projectId, $post)
                );
                $this->tpl->setNotification(
                    $result['ok'] ? 'Onboarding session saved to Manifest.' : ($result['error'] ?? 'Could not save onboarding session.'),
                    $result['ok'] ? 'success' : 'error'
                );

                return Frontcontroller::redirect($this->currentUrl($projectId, $subjectType, $templateKey, (string) ($post['phase'] ?? '')));
            }

            if ($action === 'applySync') {
                $result = $this->manifestOnboarding->syncApply([
                    'subjectType' => $subjectType,
                    'subjectKey' => $subject['key'] ?? null,
                    'subjectName' => $subject['name'] ?? null,
                    'projectId' => $projectId,
                    'includeDocs' => ! empty($post['includeDocs']),
                    'includeFiles' => ! empty($post['includeFiles']),
                ]);
                $this->tpl->setNotification(
                    $result['ok'] ? 'Reviewed sync applied through Manifest.' : ($result['error'] ?? 'Could not apply reviewed sync.'),
                    $result['ok'] ? 'success' : 'error'
                );

                return Frontcontroller::redirect($this->currentUrl($projectId, $subjectType, $templateKey, (string) ($post['phase'] ?? '')));
            }
        }

        $gatewayErrors = [];
        $template = [];
        $sessionData = [];
        $draftData = [];
        $previewData = [];

        if (! $this->manifestOnboarding->isConfigured()) {
            $gatewayErrors[] = 'Manifest onboarding is not configured. Set LEAN_MANIFEST_BASE_URL to enable this module.';
        } else {
            $templateResult = $this->manifestOnboarding->templateExport($templateKey);
            if ($templateResult['ok']) {
                $template = $templateResult['data'];
            } else {
                $gatewayErrors[] = $templateResult['error'] ?? 'Could not load questionnaire template.';
            }

            $sessionResult = $this->manifestOnboarding->sessionRead($templateKey, $externalRef);
            if ($sessionResult['ok']) {
                $sessionData = $sessionResult['data'];
            } else {
                $gatewayErrors[] = $sessionResult['error'] ?? 'Could not load saved onboarding session.';
            }

            $draftResult = $this->manifestOnboarding->draftRead($subjectType, $subject['key'] ?? null, $subject['name'] ?? null);
            if ($draftResult['ok']) {
                $draftData = $draftResult['data'];
            } else {
                $gatewayErrors[] = $draftResult['error'] ?? 'Could not load current draft state.';
            }

            $previewResult = $this->manifestOnboarding->syncPreview($subjectType, $subject['key'] ?? null, $subject['name'] ?? null, $projectId);
            if ($previewResult['ok']) {
                $previewData = $previewResult['data'];
            } else {
                $gatewayErrors[] = $previewResult['error'] ?? 'Could not load sync preview.';
            }
        }

        $phases = $this->normalizePhases($template);
        $selectedPhaseKey = $this->resolvePhaseKey($params, $phases);
        $selectedPhase = $this->findPhase($selectedPhaseKey, $phases);
        $answers = $this->extractAnswers($sessionData);

        $this->tpl->assign('onboardingProject', $project);
        $this->tpl->assign('onboardingSubjectType', $subjectType);
        $this->tpl->assign('onboardingSubject', $subject);
        $this->tpl->assign('onboardingTemplateKey', $templateKey);
        $this->tpl->assign('onboardingExternalRef', $externalRef);
        $this->tpl->assign('onboardingPhases', $phases);
        $this->tpl->assign('onboardingSelectedPhaseKey', $selectedPhaseKey);
        $this->tpl->assign('onboardingSelectedPhase', $selectedPhase);
        $this->tpl->assign('onboardingAnswers', $answers);
        $this->tpl->assign('onboardingSession', $sessionData['session'] ?? []);
        $this->tpl->assign('onboardingDraft', $draftData['draft'] ?? []);
        $this->tpl->assign('onboardingDraftMapping', $draftData['mapping'] ?? []);
        $this->tpl->assign('onboardingPreview', $previewData);
        $this->tpl->assign('onboardingGatewayErrors', array_values(array_unique(array_filter($gatewayErrors))));
        $this->tpl->assign('manifestConfigured', $this->manifestOnboarding->isConfigured());
        $this->tpl->assign('manifestWriteEnabled', $this->manifestOnboarding->supportsWrite());

        return $this->tpl->display('onboarding.project');
    }

    private function resolveSubjectType(array $params): string
    {
        $subjectType = trim((string) ($params['subjectType'] ?? 'project'));

        return in_array($subjectType, ['project', 'organization', 'person'], true) ? $subjectType : 'project';
    }

    private function resolveSubject(string $subjectType, array $project): array
    {
        if ($subjectType === 'person') {
            return [
                'type' => 'person',
                'key' => 'leantime_user_'.(int) session('userdata.id'),
                'name' => trim((string) session('userdata.firstname').' '.(string) session('userdata.lastname')),
                'label' => 'Current User',
            ];
        }

        if ($subjectType === 'organization') {
            $clientId = (int) ($project['clientId'] ?? 0);
            $client = $clientId > 0 ? $this->clientService->get($clientId) : false;

            return [
                'type' => 'organization',
                'key' => $clientId > 0 ? 'leantime_client_'.$clientId : 'leantime_project_clientless_'.(int) $project['id'],
                'name' => is_array($client) ? (string) ($client['name'] ?? $project['name']) : (string) $project['name'],
                'label' => 'Client / Organization',
            ];
        }

        return [
            'type' => 'project',
            'key' => 'leantime_project_'.(int) $project['id'],
            'name' => (string) $project['name'],
            'label' => 'Current Project',
        ];
    }

    private function buildExternalRef(string $subjectType, array $subject): string
    {
        return 'leantime-'.$subjectType.'-'.preg_replace('/[^a-z0-9\-]+/i', '-', (string) ($subject['key'] ?? 'subject')).'-onboarding';
    }

    private function buildSessionPayload(string $templateKey, string $subjectType, array $subject, int $projectId, array $post): array
    {
        $phaseLookup = [];
        foreach ($this->normalizePhases($this->manifestOnboarding->templateExport($templateKey)['data'] ?? []) as $phase) {
            foreach (($phase['questions'] ?? []) as $question) {
                $phaseLookup[(string) ($question['key'] ?? '')] = [
                    'stage' => $phase['key'] ?? 'theory',
                    'prompt' => $question['prompt'] ?? '',
                ];
            }
        }

        $answers = [];
        foreach (($post['answers'] ?? []) as $questionKey => $answerText) {
            $questionKey = trim((string) $questionKey);
            if ($questionKey === '') {
                continue;
            }

            $metadata = $phaseLookup[$questionKey] ?? ['stage' => 'theory', 'prompt' => $questionKey];
            $answers[] = [
                'questionKey' => $questionKey,
                'prompt' => $metadata['prompt'],
                'stage' => $metadata['stage'],
                'answerText' => trim((string) $answerText),
            ];
        }

        return [
            'subject' => [
                'type' => $subjectType,
                'key' => $subject['key'] ?? null,
                'name' => $subject['name'] ?? null,
            ],
            'template' => [
                'key' => $templateKey,
                'name' => $templateKey,
                'subjectKind' => $subjectType,
            ],
            'session' => [
                'externalRef' => $this->buildExternalRef($subjectType, $subject),
                'status' => trim((string) ($post['sessionStatus'] ?? 'draft')),
                'sourcePath' => 'leantime-module://onboarding/project/'.$projectId,
                'alignment' => [
                    'project' => [
                        'id' => $projectId,
                        'name' => $subject['name'] ?? null,
                    ],
                    'confidence' => 'module',
                ],
            ],
            'answers' => $answers,
            'artifacts' => [],
        ];
    }

    private function currentUrl(int $projectId, string $subjectType, string $templateKey, string $phase): string
    {
        $query = http_build_query(array_filter([
            'projectId' => $projectId,
            'subjectType' => $subjectType,
            'templateKey' => $templateKey,
            'phase' => $phase !== '' ? $phase : null,
        ]));

        return BASE_URL.'/onboarding/project'.($query !== '' ? '?'.$query : '');
    }

    private function normalizePhases(array $template): array
    {
        $phases = $template['phases'] ?? [];

        return is_array($phases) ? array_values(array_filter($phases, static fn ($phase) => is_array($phase))) : [];
    }

    private function resolvePhaseKey(array $params, array $phases): string
    {
        $requested = trim((string) ($params['phase'] ?? ''));
        if ($requested !== '') {
            foreach ($phases as $phase) {
                if (($phase['key'] ?? '') === $requested) {
                    return $requested;
                }
            }
        }

        return (string) ($phases[0]['key'] ?? '');
    }

    private function findPhase(string $phaseKey, array $phases): array
    {
        foreach ($phases as $phase) {
            if (($phase['key'] ?? '') === $phaseKey) {
                return $phase;
            }
        }

        return $phases[0] ?? [];
    }

    private function extractAnswers(array $sessionData): array
    {
        $mapped = [];
        foreach (($sessionData['answers'] ?? []) as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $key = (string) ($answer['question_key'] ?? $answer['questionKey'] ?? '');
            if ($key === '') {
                continue;
            }

            $mapped[$key] = (string) ($answer['answer_text'] ?? $answer['answerText'] ?? '');
        }

        return $mapped;
    }
}
