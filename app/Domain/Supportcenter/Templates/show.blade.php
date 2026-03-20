<x-global::pageheader :icon="'fa fa-life-ring'">
    Ticket #{{ $ticket->id }}
</x-global::pageheader>

<div class="maincontent">
    <div class="maincontentinner">
        <p>
            <a class="btn btn-default" href="{{ BASE_URL }}/support-center?projectId={{ $ticket->projectId }}">Back to Tickets</a>
        </p>

        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>{{ $ticket->headline }}</strong>
            </div>
            <div class="panel-body">
                <p><strong>Status:</strong> {{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</p>
                <p><strong>Updated:</strong> {{ $ticket->modified }}</p>
                <hr />
                <div style="line-height:1.65;">{!! nl2br(e(strip_tags((string) $ticket->description))) !!}</div>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Engineering Elevation</strong>
            </div>
            <div class="panel-body">
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
                            <textarea id="githubSummary" name="githubSummary" rows="5" class="form-control" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="githubReproduction">Reproduction Notes</label>
                            <textarea id="githubReproduction" name="githubReproduction" rows="4" class="form-control"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="githubImpact">Impact</label>
                            <textarea id="githubImpact" name="githubImpact" rows="3" class="form-control"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Elevate to GitHub</button>
                    </form>
                @else
                    <p class="text-muted">Only manager-level users and above can elevate support tickets to GitHub.</p>
                @endif
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Comments</strong>
            </div>
            <div class="panel-body">
                @if(count($comments) === 0)
                    <p class="text-muted">No comments yet.</p>
                @else
                    @foreach($comments as $comment)
                        <div style="padding:14px 0; border-top:1px solid #e4e7ec;">
                            <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:8px;">
                                <strong>{{ trim(($comment['firstname'] ?? '').' '.($comment['lastname'] ?? '')) }}</strong>
                                <span class="text-muted">{{ $comment['date'] }}</span>
                            </div>
                            <div style="line-height:1.65;">{!! nl2br(e(strip_tags((string) ($comment['text'] ?? '')))) !!}</div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>Add Comment</strong>
            </div>
            <div class="panel-body">
                <form method="post" action="{{ BASE_URL }}/support-center/{{ $ticket->id }}?projectId={{ $ticket->projectId }}">
                    <div class="form-group">
                        <textarea name="text" rows="6" class="form-control" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Post Comment</button>
                </form>
            </div>
        </div>
    </div>
</div>
