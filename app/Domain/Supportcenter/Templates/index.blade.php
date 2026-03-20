@extends($layout)

@section('content')
<x-global::pageheader :icon="'fa fa-life-ring'">
    <h5>Customer Support</h5>
    <h1 class="articleHeadline">Support Center</h1>
</x-global::pageheader>

@include('supportcenter::partials.assetStyles')

<div class="maincontent">
    {!! $tpl->displayNotification() !!}

    @if(count($supportProjects) === 0)
        <div class="maincontentinner">
            <div class="alert alert-info">
                No support projects are currently available for your account.
            </div>
        </div>
    @else
        <div class="maincontentinner">
            <div class="supportcenter-surface supportcenter-overview">
                <div class="supportcenter-overview-copy">
                    <h5 class="subtitle">Internal Ticket Intake</h5>
                    <p class="supportcenter-lead">Track customer-reported issues, review current support load, and escalate validated engineering work without leaving the app.</p>
                </div>
                <div class="supportcenter-toolbar">
                    <form method="get" action="{{ BASE_URL }}/support-center" class="form-inline">
                        <label for="projectId" style="margin-right:10px;"><strong>Support Project</strong></label>
                        <select id="projectId" name="projectId" onchange="this.form.submit()" class="form-control">
                            @foreach($supportProjects as $projectOption)
                                <option value="{{ $projectOption['id'] }}" @selected(($selectedSupportProject['id'] ?? null) === $projectOption['id'])>
                                    {{ $projectOption['name'] }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    <a class="btn btn-primary" href="{{ BASE_URL }}/support-center/new?projectId={{ $selectedSupportProject['id'] ?? '' }}">New Support Ticket</a>
                </div>
                <div class="supportcenter-meta-row">
                    <span class="supportcenter-stat">
                        <strong>{{ count($openTickets) }}</strong>
                        <span>Open</span>
                    </span>
                    <span class="supportcenter-stat">
                        <strong>{{ count($archivedTickets) }}</strong>
                        <span>Archived</span>
                    </span>
                    <span class="supportcenter-stat supportcenter-stat-project">
                        <strong>Project</strong>
                        <span>{{ $selectedSupportProject['name'] ?? 'Support' }}</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="row supportcenter-grid-row">
            <div class="col-md-6">
                <div class="maincontentinner supportcenter-section-wrap">
                    <div class="supportcenter-surface">
                        <div class="supportcenter-section-header">
                            <h5 class="subtitle">Open Tickets</h5>
                            <span class="supportcenter-count">{{ count($openTickets) }}</span>
                        </div>
                        @if(count($openTickets) === 0)
                            <div class="supportcenter-empty">
                                <strong>No open support tickets</strong>
                                <p>New customer or internal support requests will appear here as they come in.</p>
                            </div>
                        @else
                            <div class="supportcenter-list">
                                @foreach($openTickets as $ticket)
                                    <a class="supportcenter-card" href="{{ BASE_URL }}/support-center/{{ $ticket->id }}?projectId={{ $ticket->projectId }}">
                                        <div class="supportcenter-meta">
                                            <strong>#{{ $ticket->id }} {{ $ticket->headline }}</strong>
                                            <span>{{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</span>
                                        </div>
                                        <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $ticket->description), 180) }}</p>
                                        <div class="supportcenter-meta">
                                            <span>Priority: {{ $priorities[$ticket->priority] ?? $ticket->priority ?? 'Unspecified' }}</span>
                                            <span>Updated: {{ $ticket->modified }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="maincontentinner supportcenter-section-wrap">
                    <div class="supportcenter-surface">
                        <div class="supportcenter-section-header">
                            <h5 class="subtitle">Archived / Resolved</h5>
                            <span class="supportcenter-count">{{ count($archivedTickets) }}</span>
                        </div>
                        @if(count($archivedTickets) === 0)
                            <div class="supportcenter-empty">
                                <strong>No archived tickets yet</strong>
                                <p>Resolved or closed support issues will collect here for reference.</p>
                            </div>
                        @else
                            <div class="supportcenter-list">
                                @foreach($archivedTickets as $ticket)
                                    <a class="supportcenter-card archived" href="{{ BASE_URL }}/support-center/{{ $ticket->id }}?projectId={{ $ticket->projectId }}">
                                        <div class="supportcenter-meta">
                                            <strong>#{{ $ticket->id }} {{ $ticket->headline }}</strong>
                                            <span>{{ $statusLabels[$ticket->status]['name'] ?? $ticket->status }}</span>
                                        </div>
                                        <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $ticket->description), 180) }}</p>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
