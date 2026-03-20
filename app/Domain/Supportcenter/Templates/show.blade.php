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
