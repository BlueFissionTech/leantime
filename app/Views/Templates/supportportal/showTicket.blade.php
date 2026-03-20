@extends($layout)

@section('content')
<div class="support-page">
    <div class="support-page-header">
        <div>
            <span class="support-eyebrow">Ticket #{{ $ticket->id }}</span>
            <h1>{{ $ticket->headline }}</h1>
            <div class="support-ticket-meta">
                <span>Status: {{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</span>
                <span>Updated: {{ $ticket->modified }}</span>
            </div>
        </div>
        <a class="support-button secondary" href="{{ $supportTicketsUrl }}">Back to Tickets</a>
    </div>

    <section class="support-panel support-ticket-body">
        <h2>Description</h2>
        <div class="support-richtext">{!! $tpl->escapeMinimal((string) $ticket->description) !!}</div>
    </section>

    <section class="support-panel support-ticket-body">
        <h2>Comments</h2>
        @if(count($comments) === 0)
            <p class="support-empty">No comments yet.</p>
        @else
            <div class="support-comment-list">
                @foreach($comments as $comment)
                    <article class="support-comment">
                        <div class="support-comment-meta">
                            <strong>{{ trim(($comment['firstname'] ?? '').' '.($comment['lastname'] ?? '')) }}</strong>
                            <span>{{ $comment['date'] }}</span>
                        </div>
                        <div class="support-richtext">{!! $tpl->escapeMinimal((string) ($comment['text'] ?? '')) !!}</div>
                    </article>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ $supportTicketsUrl }}/{{ $ticket->id }}" class="support-form support-comment-form support-editor">
            <input type="hidden" name="ticketId" value="{{ $ticket->id }}" />
            <label>
                <span>Add Comment</span>
                <textarea name="text" rows="6" class="tiptapSimple" required></textarea>
            </label>
            <button type="submit" class="support-button primary">Post Comment</button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.leantime && window.leantime.tiptapController) {
            window.leantime.tiptapController.initSimpleEditor();
        }
    });
</script>
@endpush
