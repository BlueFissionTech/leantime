@props([
    'tickets' => [],
    'projectFilter' => '',
    'totalCandidates' => 0,
    'focusCount' => 0,
    'expectedCount' => 0,
    'assessmentCount' => 0,
    'provisionedCount' => 0,
])

<div id="highImpactNextWidget"
     hx-get="{{ BASE_URL }}/widgets/highImpactNext/get"
     hx-trigger="HTMX.updateProjectList from:body, {{ \Leantime\Domain\Tickets\Htmx\HtmxTicketEvents::UPDATE }} from:body, {{ \Leantime\Domain\Tickets\Htmx\HtmxTicketEvents::SUBTASK_UPDATE }} from:body"
     hx-target="#highImpactNextWidget"
     hx-swap="outerHTML"
     class="tw-flex tw-flex-col tw-gap-m">

    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-sm tw-rounded-xl tw-border tw-border-gray-200 tw-bg-slate-50 tw-p-m">
        <div class="subtitle tw-mb-xs">{{ __('headlines.high_impact_next') }}</div>
        <div class="tw-flex tw-flex-wrap tw-gap-xs tw-text-xs">
            <span class="label">{{ $totalCandidates }} Tasks</span>
            @if($focusCount > 0)
                <span class="label label-info">Focus {{ $focusCount }}</span>
            @endif
            @if($expectedCount > 0)
                <span class="label label-warning">Expected {{ $expectedCount }}</span>
            @endif
            @if($assessmentCount > 0)
                <span class="label">Assessment {{ $assessmentCount }}</span>
            @endif
            @if($provisionedCount > 0)
                <span class="label label-success">Provisioned {{ $provisionedCount }}</span>
            @endif
        </div>
    </div>

    @if(count($tickets) === 0)
        <div class="tw-rounded-xl tw-border tw-border-dashed tw-border-gray-300 tw-bg-white tw-p-l tw-text-center tw-text-gray-600">
            {{ __('text.high_impact_next_empty') }}
        </div>
    @endif

    @foreach($tickets as $ticket)
        @php($highImpact = $ticket['highImpact'] ?? [])
        <a href="{{ BASE_URL }}/tickets/showTicket/{{ $ticket['id'] }}"
           class="projectBox tw-block tw-rounded-xl tw-border tw-border-gray-200 tw-bg-white tw-p-m tw-no-underline hover:tw-no-underline">
            <div class="tw-flex tw-items-start tw-justify-between tw-gap-sm">
                <div class="tw-min-w-0 tw-flex-1">
                    <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-xs tw-mb-xs tw-max-w-full">
                        <span class="label label-default">#{{ $ticket['id'] }}</span>
                        @if(($highImpact['focus'] ?? false) === true)
                            <span class="label label-info">Focus</span>
                        @endif
                        @if(($highImpact['expected'] ?? false) === true)
                            <span class="label label-warning">Expected</span>
                        @endif
                        @if(($highImpact['assessment'] ?? false) === true)
                            <span class="label">Assessment</span>
                        @endif
                        @if(!empty($highImpact['impactLabel']))
                            <span class="label label-important">Impact: {{ ucfirst($highImpact['impactLabel']) }}</span>
                        @endif
                        @if(!empty($highImpact['provisionRef']))
                            <span class="label label-success tw-whitespace-normal tw-break-all tw-max-w-full">{{ $highImpact['provisionRef'] === 'provisioned' ? 'Provisioned' : 'Provision: '.$highImpact['provisionRef'] }}</span>
                        @endif
                    </div>
                    <div class="tw-font-semibold tw-text-slate-900 tw-break-words">{{ $ticket['headline'] }}</div>
                    <div class="tw-mt-xs tw-text-sm tw-text-gray-600 tw-break-words">
                        {{ $ticket['projectName'] ?? __('labels.all_projects') }}
                        @if(!empty($ticket['milestoneHeadline']))
                            · {{ $ticket['milestoneHeadline'] }}
                        @endif
                    </div>
                    @if(!empty($highImpact['focusNote']))
                        <div class="tw-mt-xs tw-text-sm tw-text-slate-500 tw-break-words">
                            {{ $highImpact['focusNote'] }}
                        </div>
                    @endif
                </div>
                <div class="tw-text-right tw-text-xs tw-text-gray-500 tw-min-w-[88px] tw-shrink-0">
                    <div class="tw-font-semibold tw-text-slate-700">Score {{ $highImpact['score'] ?? 0 }}</div>
                    @if(($highImpact['dueState'] ?? null) === 'overdue')
                        <div class="tw-text-red-600">Overdue</div>
                    @elseif(($highImpact['dueState'] ?? null) === 'dueSoon')
                        <div class="tw-text-amber-600">Due Soon</div>
                    @elseif(!empty($ticket['dateToFinish']) && !in_array($ticket['dateToFinish'], ['0000-00-00 00:00:00', '1969-12-31 00:00:00'], true))
                        <div>{{ \Carbon\CarbonImmutable::parse($ticket['dateToFinish'])->format('M j') }}</div>
                    @endif
                </div>
            </div>
        </a>
    @endforeach
</div>
