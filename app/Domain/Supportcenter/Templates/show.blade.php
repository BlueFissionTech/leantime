<x-global::pageheader :icon="'fa fa-life-ring'">
    <h5>Customer Support</h5>
    <h1 class="articleHeadline">Ticket #{{ $ticket->id }}</h1>
</x-global::pageheader>

@include('supportcenter::partials.assetStyles')

<div class="maincontent">
    {!! $tpl->displayNotification() !!}

    <div class="maincontentinner">
        <div class="supportcenter-surface supportcenter-overview">
            <div class="supportcenter-overview-copy">
                <h5 class="subtitle">Ticket Review</h5>
                <p class="supportcenter-lead">Review the support thread, coordinate next steps, and escalate validated engineering issues when needed.</p>
            </div>
        </div>
    </div>

    <div class="maincontentinner">
        <div class="supportcenter-surface">
            <p>
                <a class="btn btn-default" href="{{ BASE_URL }}/support-center?projectId={{ $ticket->projectId }}">Back to Tickets</a>
            </p>

            <h3>{{ $ticket->headline }}</h3>
            <p><strong>Status:</strong> {{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</p>
            <p><strong>Updated:</strong> {{ $ticket->modified }}</p>
            <hr />
            <div class="supportcenter-richtext">{!! $tpl->escapeMinimal((string) $ticket->description) !!}</div>
        </div>
    </div>

    <div class="maincontentinner">
        <div class="supportcenter-surface">
            <h5 class="subtitle">Engineering Elevation</h5>
            @if($githubIssue !== false)
                <p><strong>Status:</strong> Elevated to GitHub</p>
                <p><strong>Issue:</strong> <a href="{{ $githubIssue['url'] }}" target="_blank" rel="noopener noreferrer">#{{ $githubIssue['number'] }}</a></p>
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

    <div class="maincontentinner">
        <div class="supportcenter-surface">
            <h5 class="subtitle">Comments</h5>
            @if(count($comments) === 0)
                <div class="supportcenter-empty">
                    <strong>No comments yet</strong>
                    <p>Use the form below to add context, updates, or a next step.</p>
                </div>
            @else
                @foreach($comments as $comment)
                    <div class="supportcenter-comment">
                        <div class="supportcenter-meta supportcenter-comment-meta">
                            <strong>{{ trim(($comment['firstname'] ?? '').' '.($comment['lastname'] ?? '')) }}</strong>
                            <span class="text-muted">{{ $comment['date'] }}</span>
                        </div>
                        <div class="supportcenter-richtext">{!! $tpl->escapeMinimal((string) ($comment['text'] ?? '')) !!}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="maincontentinner supportcenter-editor">
        <div class="supportcenter-surface">
            <h5 class="subtitle">Add Comment</h5>
            <form method="post" action="{{ BASE_URL }}/support-center/{{ $ticket->id }}?projectId={{ $ticket->projectId }}">
                <div class="form-group">
                    <textarea name="text" rows="6" class="form-control tiptapSimple" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Post Comment</button>
            </form>
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
