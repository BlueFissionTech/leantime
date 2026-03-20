<x-global::pageheader :icon="'fa fa-life-ring'">
    Ticket #{{ $ticket->id }}
</x-global::pageheader>

@include('supportcenter.partials.styles')

<div class="maincontent">
    <div class="maincontentinner supportcenter-shell">
        <p>
            <a class="btn btn-default" href="{{ BASE_URL }}/support-center?projectId={{ $ticket->projectId }}">Back to Tickets</a>
        </p>

        <div class="supportcenter-panel">
            <div>
                <strong>{{ $ticket->headline }}</strong>
            </div>
            <div>
                <p><strong>Status:</strong> {{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</p>
                <p><strong>Updated:</strong> {{ $ticket->modified }}</p>
                <hr />
                <div class="supportcenter-richtext">{!! $tpl->escapeMinimal((string) $ticket->description) !!}</div>
            </div>
        </div>

        <div class="supportcenter-panel">
            <div>
                <strong>Engineering Elevation</strong>
            </div>
            <div>
                @if($githubIssue !== false)
                    <p>
                        <strong>Status:</strong> Elevated to GitHub
                    </p>
                    <p>
                        <strong>Issue:</strong>
                        <a href="{{ $githubIssue['url'] }}" target="_blank" rel="noopener noreferrer">#{{ $githubIssue['number'] }}</a>
                    </p>
                @elseif($canElevateGitHub)
                    <p class="text-muted">Use this when the issue has been validated as an engineering/code issue. Keep the GitHub text sanitized for public visibility.</p>
                    <form method="post" action="{{ BASE_URL }}/support-center/{{ $ticket->id }}/elevate-github?projectId={{ $ticket->projectId }}">
                        <div class="form-group">
                            <label for="githubTitle">GitHub Title</label>
                            <input id="githubTitle" type="text" name="githubTitle" class="form-control" required />
                        </div>
                        <div class="form-group">
                            <label for="githubSummary">Technical Summary</label>
                            <textarea id="githubSummary" name="githubSummary" rows="5" class="form-control tiptapComplex" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="githubReproduction">Reproduction Notes</label>
                            <textarea id="githubReproduction" name="githubReproduction" rows="4" class="form-control tiptapSimple"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="githubImpact">Impact</label>
                            <textarea id="githubImpact" name="githubImpact" rows="3" class="form-control tiptapSimple"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Elevate to GitHub</button>
                    </form>
                @else
                    <p class="text-muted">Only manager-level users and above can elevate support tickets to GitHub.</p>
                @endif
            </div>
        </div>

        <div class="supportcenter-panel">
            <div>
                <strong>Comments</strong>
            </div>
            <div>
                @if(count($comments) === 0)
                    <p class="text-muted">No comments yet.</p>
                @else
                    @foreach($comments as $comment)
                        <div class="supportcenter-comment">
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                                <strong>{{ trim(($comment['firstname'] ?? '').' '.($comment['lastname'] ?? '')) }}</strong>
                                <span class="text-muted">{{ $comment['date'] }}</span>
                            </div>
                            <div class="supportcenter-richtext">{!! $tpl->escapeMinimal((string) ($comment['text'] ?? '')) !!}</div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="supportcenter-panel">
            <div>
                <strong>Add Comment</strong>
            </div>
            <div>
                <form method="post" action="{{ BASE_URL }}/support-center/{{ $ticket->id }}?projectId={{ $ticket->projectId }}" class="supportcenter-editor">
                    <div class="form-group">
                        <textarea name="text" rows="6" class="form-control tiptapSimple" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.leantime && window.leantime.tiptapController) {
            window.leantime.tiptapController.initComplexEditor();
            window.leantime.tiptapController.initSimpleEditor();
        }
    });
</script>
@endpush
