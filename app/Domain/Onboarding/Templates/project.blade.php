@extends($layout)

@section('content')
<x-global::pageheader :icon="'fa fa-compass'">
    <h5>Manifest Onboarding</h5>
    <h1 class="articleHeadline">Discovery Intake</h1>
</x-global::pageheader>

<div class="maincontent">
    {!! $tpl->displayNotification() !!}

    <div class="maincontentinner">
        <style>
            .onboarding-shell { display:grid; gap:20px; }
            .onboarding-surface { background:#fff; border:1px solid #dbe3ec; border-radius:14px; padding:20px; box-shadow:0 10px 30px rgba(15,23,42,.04); }
            .onboarding-meta { display:flex; flex-wrap:wrap; gap:12px 18px; color:#556371; margin-top:12px; }
            .onboarding-phase-list { display:grid; gap:10px; }
            .onboarding-phase-link { display:block; border:1px solid #dbe3ec; border-radius:12px; padding:12px 14px; color:inherit; text-decoration:none; }
            .onboarding-phase-link.active { border-color:#1f5f99; background:#eef5fb; }
            .onboarding-question { margin-bottom:16px; }
            .onboarding-question textarea { width:100%; min-height:110px; }
            .onboarding-grid { display:grid; gap:20px; grid-template-columns:280px minmax(0,1fr) 320px; }
            .onboarding-note { color:#667085; font-size:13px; }
            .onboarding-summary { display:grid; gap:10px; }
            .onboarding-pill { display:inline-block; margin-right:8px; margin-bottom:8px; padding:4px 10px; border-radius:999px; background:#eef2f7; color:#334155; }
            .onboarding-error { margin-bottom:10px; }
            .onboarding-actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:16px; }
            .onboarding-json { max-height:260px; overflow:auto; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:12px; font-size:12px; }
            @media (max-width: 1200px) { .onboarding-grid { grid-template-columns:1fr; } }
        </style>

        <div class="onboarding-shell">
            <div class="onboarding-surface">
                <form method="get" action="{{ BASE_URL }}/onboarding/project" class="form-inline">
                    <input type="hidden" name="projectId" value="{{ $onboardingProject['id'] }}" />
                    <label for="subjectType" style="margin-right:8px;"><strong>Subject</strong></label>
                    <select id="subjectType" name="subjectType" class="form-control" style="margin-right:12px;">
                        <option value="project" @selected($onboardingSubjectType === 'project')>Project</option>
                        <option value="organization" @selected($onboardingSubjectType === 'organization')>Organization</option>
                        <option value="person" @selected($onboardingSubjectType === 'person')>Person</option>
                    </select>
                    <label for="templateKey" style="margin-right:8px;"><strong>Template</strong></label>
                    <input id="templateKey" type="text" name="templateKey" value="{{ $onboardingTemplateKey }}" class="form-control" style="margin-right:12px; min-width:240px;" />
                    <button type="submit" class="btn btn-primary">Load Intake</button>
                </form>
                <div class="onboarding-meta">
                    <span><strong>Project:</strong> {{ $onboardingProject['name'] }}</span>
                    <span><strong>Subject:</strong> {{ $onboardingSubject['label'] ?? ucfirst($onboardingSubjectType) }} - {{ $onboardingSubject['name'] ?? 'Unresolved' }}</span>
                    <span><strong>External Ref:</strong> {{ $onboardingExternalRef }}</span>
                    <span><strong>Write Transport:</strong> {{ $manifestWriteEnabled ? 'Enabled' : 'Read-only' }}</span>
                </div>
            </div>

            @foreach($onboardingGatewayErrors as $gatewayError)
                <div class="alert alert-warning onboarding-error">{{ $gatewayError }}</div>
            @endforeach

            <div class="onboarding-grid">
                <div class="onboarding-surface">
                    <h5 class="subtitle">Phases</h5>
                    <div class="onboarding-phase-list">
                        @forelse($onboardingPhases as $phase)
                            <a class="onboarding-phase-link @if(($phase['key'] ?? '') === $onboardingSelectedPhaseKey) active @endif"
                               href="{{ BASE_URL }}/onboarding/project?projectId={{ $onboardingProject['id'] }}&subjectType={{ $onboardingSubjectType }}&templateKey={{ urlencode($onboardingTemplateKey) }}&phase={{ $phase['key'] }}">
                                <strong>{{ $phase['label'] ?? ucfirst($phase['key'] ?? 'Phase') }}</strong><br />
                                <span class="onboarding-note">{{ count($phase['questions'] ?? []) }} questions</span>
                            </a>
                        @empty
                            <div class="onboarding-note">No phases loaded yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="onboarding-surface">
                    <h5 class="subtitle">Survey Runner</h5>
                    @if(empty($onboardingSelectedPhase))
                        <p class="onboarding-note">Load a questionnaire template to begin.</p>
                    @else
                        <h3>{{ $onboardingSelectedPhase['label'] ?? ucfirst($onboardingSelectedPhase['key'] ?? 'Phase') }}</h3>
                        <p class="onboarding-note">Manifest remains the source of truth for question structure, drafts, and sync planning. This LeanTime module is only the shell around that contract.</p>

                        <form method="post" action="{{ BASE_URL }}/onboarding/project?projectId={{ $onboardingProject['id'] }}&subjectType={{ $onboardingSubjectType }}&templateKey={{ urlencode($onboardingTemplateKey) }}&phase={{ $onboardingSelectedPhaseKey }}">
                            <input type="hidden" name="action" value="saveSession" />
                            <input type="hidden" name="phase" value="{{ $onboardingSelectedPhaseKey }}" />
                            <input type="hidden" name="sessionStatus" value="draft" />
                            @foreach(($onboardingSelectedPhase['questions'] ?? []) as $index => $question)
                                <div class="onboarding-question">
                                    <label for="answer_{{ $question['key'] ?? $index }}"><strong>{{ $question['prompt'] ?? $question['key'] ?? 'Question' }}</strong></label>
                                    <textarea id="answer_{{ $question['key'] ?? $index }}" name="answers[{{ $question['key'] ?? $index }}]" class="form-control">{{ $onboardingAnswers[$question['key'] ?? ''] ?? '' }}</textarea>
                                    <div class="onboarding-note">
                                        Phase: {{ $question['stage'] ?? $onboardingSelectedPhaseKey }}
                                        @if(!empty($question['evidenceAllowed'])) | Evidence attachment is planned for the next slice @endif
                                    </div>
                                </div>
                            @endforeach

                            <div class="onboarding-actions">
                                @if($manifestWriteEnabled)
                                    <button type="submit" class="btn btn-primary">Save Draft to Manifest</button>
                                @else
                                    <button type="button" class="btn btn-default" disabled>Save Draft Disabled</button>
                                @endif
                            </div>
                        </form>
                    @endif
                </div>

                <div class="onboarding-surface">
                    <h5 class="subtitle">Review + Preview</h5>
                    <div class="onboarding-summary">
                        <div>
                            <strong>Session</strong><br />
                            <span class="onboarding-note">{{ $onboardingSession['status'] ?? 'No saved session' }}</span>
                        </div>
                        <div>
                            <strong>Draft</strong><br />
                            <span class="onboarding-note">{{ $onboardingDraft['status'] ?? 'No draft' }}</span>
                        </div>
                        <div>
                            <strong>Resolved Project</strong><br />
                            <span class="onboarding-note">{{ $onboardingPreview['mapping']['resolvedProjectId'] ?? $onboardingDraftMapping['targetProjectId'] ?? 'Unmapped' }}</span>
                        </div>
                        <div>
                            <strong>Preview Warnings</strong><br />
                            @forelse(($onboardingPreview['sync']['warnings'] ?? []) as $warning)
                                <span class="onboarding-pill">{{ $warning }}</span>
                            @empty
                                <span class="onboarding-note">No preview warnings.</span>
                            @endforelse
                        </div>
                        <div>
                            <strong>Canvas Plan</strong><br />
                            <span class="onboarding-note">{{ count($onboardingPreview['sync']['canvases'] ?? []) }} planned canvas operations</span>
                        </div>
                        <div>
                            <strong>Publication Plan</strong><br />
                            <span class="onboarding-note">{{ count($onboardingPreview['sync']['publication'] ?? []) }} publication actions</span>
                        </div>
                    </div>

                    @if($manifestWriteEnabled)
                        <form method="post" action="{{ BASE_URL }}/onboarding/project?projectId={{ $onboardingProject['id'] }}&subjectType={{ $onboardingSubjectType }}&templateKey={{ urlencode($onboardingTemplateKey) }}&phase={{ $onboardingSelectedPhaseKey }}">
                            <input type="hidden" name="action" value="applySync" />
                            <input type="hidden" name="phase" value="{{ $onboardingSelectedPhaseKey }}" />
                            <label class="onboarding-note"><input type="checkbox" name="includeDocs" value="1" checked /> Include docs</label><br />
                            <label class="onboarding-note"><input type="checkbox" name="includeFiles" value="1" checked /> Include files</label>
                            <div class="onboarding-actions">
                                <button type="submit" class="btn btn-primary">Apply Reviewed Sync</button>
                            </div>
                        </form>
                    @else
                        <div class="onboarding-note" style="margin-top:16px;">Write/apply calls are intentionally disabled until LeanTime is configured with Manifest write transport for this environment.</div>
                    @endif

                    @if(!empty($onboardingPreview))
                        <div class="onboarding-json" style="margin-top:16px;">{{ json_encode($onboardingPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
