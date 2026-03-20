@extends($layout)

@section('content')
<div class="support-page">
    <div class="support-page-header">
        <div>
            <span class="support-eyebrow">{{ $portal['productName'] }}</span>
            <h1>My Support Tickets</h1>
        </div>
        <a class="support-button primary" href="{{ BASE_URL }}/support/tickets/new">New Ticket</a>
    </div>

    <section class="support-ticket-section">
        <h2>Open</h2>
        @if(count($openTickets) === 0)
            <p class="support-empty">No open tickets right now.</p>
        @else
            <div class="support-ticket-list">
                @foreach($openTickets as $ticket)
                    <a class="support-ticket-card" href="{{ BASE_URL }}/support/tickets/{{ $ticket->id }}">
                        <div class="support-ticket-card-top">
                            <strong>#{{ $ticket->id }} {{ $ticket->headline }}</strong>
                            <span class="support-status-pill">{{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</span>
                        </div>
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $ticket->description), 180) }}</p>
                        <div class="support-ticket-meta">
                            <span>Priority: {{ $priorities[$ticket->priority] ?? $ticket->priority ?? 'Unspecified' }}</span>
                            <span>Updated: {{ $ticket->modified }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="support-ticket-section">
        <h2>Archived / Resolved</h2>
        @if(count($archivedTickets) === 0)
            <p class="support-empty">No archived tickets yet.</p>
        @else
            <div class="support-ticket-list">
                @foreach($archivedTickets as $ticket)
                    <a class="support-ticket-card archived" href="{{ BASE_URL }}/support/tickets/{{ $ticket->id }}">
                        <div class="support-ticket-card-top">
                            <strong>#{{ $ticket->id }} {{ $ticket->headline }}</strong>
                            <span class="support-status-pill">{{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</span>
                        </div>
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $ticket->description), 180) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
