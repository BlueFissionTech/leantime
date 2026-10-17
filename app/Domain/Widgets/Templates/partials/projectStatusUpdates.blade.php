@props([
    'updates' => [],
])

<div id="projectStatusUpdatesWidget"
     hx-get="{{ BASE_URL }}/widgets/projectStatusUpdates/get"
     hx-trigger="load, every 5m"
     hx-target="#projectStatusUpdatesWidget"
     hx-swap="outerHTML"
     class="tw-flex tw-flex-col tw-gap-m">

    @if(count($updates) === 0)
        <div class="tw-rounded-xl tw-border tw-border-dashed tw-border-gray-300 tw-bg-white tw-p-l tw-text-center tw-text-gray-600">
            {{ __('text.project_status_updates_empty') }}
        </div>
    @endif

    @foreach($updates as $update)
        @php($status = $update['status'] ?? 'green')
        @php($statusClass = match ($status) {
            'red' => 'label-important',
            'yellow' => 'label-warning',
            default => 'label-success',
        })

        <a href="{{ BASE_URL }}/projects/changeCurrentProject/{{ $update['projectId'] }}"
           class="projectBox tw-block tw-rounded-xl tw-border tw-border-gray-200 tw-bg-white tw-p-m tw-no-underline hover:tw-no-underline">
            <div class="tw-flex tw-items-start tw-justify-between tw-gap-sm">
                <div class="tw-min-w-0">
                    <div class="tw-font-semibold tw-text-slate-900 tw-break-words">{{ $update['projectName'] }}</div>
                    <div class="tw-mt-xs tw-text-sm tw-text-slate-600 tw-break-words">
                        {{ $update['firstname'] }} {{ $update['lastname'] }} · {{ \Carbon\CarbonImmutable::parse($update['date'])->format('M j, g:i A') }}
                    </div>
                </div>
                <span class="label {{ $statusClass }} tw-shrink-0">{{ ucfirst($status) }}</span>
            </div>
            <div class="tw-mt-sm tw-text-sm tw-text-slate-700 tw-break-words">
                {!! $tpl->escapeMinimal($update['text']) !!}
            </div>
        </a>
    @endforeach
</div>
