<x-global::pageheader :icon="'fa fa-life-ring'">
    Support Center
</x-global::pageheader>

@include('supportcenter::partials.assetStyles')

<div class="maincontent">
    <div class="maincontentinner">
        @if(count($supportProjects) === 0)
            <div class="alert alert-info">
                No support projects are currently available for your account.
            </div>
        @else
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

            <div class="supportcenter-columns">
                <section>
                    <h3>Open</h3>
                    @if(count($openTickets) === 0)
                        <p class="text-muted">No open support tickets right now.</p>
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
                </section>

                <section>
                    <h3>Archived / Resolved</h3>
                    @if(count($archivedTickets) === 0)
                        <p class="text-muted">No archived support tickets yet.</p>
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
                </section>
            </div>
        @endif
    </div>
</div>
