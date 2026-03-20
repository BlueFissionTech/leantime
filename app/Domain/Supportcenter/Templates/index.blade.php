<x-global::pageheader :icon="'fa fa-life-ring'">
    Support Center
</x-global::pageheader>

<div class="maincontent">
    <div class="maincontentinner">
        @if(count($supportProjects) === 0)
            <div class="alert alert-info">
                No support projects are currently available for your account.
            </div>
        @else
            <style>
                .supportcenter-grid { display:grid; gap:20px; }
                .supportcenter-toolbar { display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap; margin-bottom:18px; }
                .supportcenter-list { display:grid; gap:14px; }
                .supportcenter-card { border:1px solid #dbe3ec; border-radius:16px; padding:18px; background:#fff; box-shadow:0 10px 30px rgba(15,23,42,.05); color:inherit; text-decoration:none; }
                .supportcenter-card.archived { opacity:.78; }
                .supportcenter-meta { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:12px; color:#667085; }
                .supportcenter-columns { display:grid; gap:20px; grid-template-columns:repeat(auto-fit, minmax(320px,1fr)); }
            </style>

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
