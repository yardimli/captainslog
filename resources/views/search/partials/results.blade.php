@if($results)
    <p class="text-sm text-slate-500">{{ $results->total() }} {{ Str::plural('result', $results->total()) }} for “{{ $keyword }}”</p>
    @forelse($results as $block)
        @php
            $occurredAt = $block->taskEvent?->occurred_at ?? $block->occurred_at ?? $block->created_at;
            $resultTitle = $block->taskEvent?->task_name ?? match ($block->type) {
                'sensor_mobile_browser' => 'Mobile browsing',
                'sensor_browser' => 'Browsing',
                'sensor_desktop' => 'Desktop',
                'sensor_github' => $block->content ?: 'GitHub activity',
                'sensor_google_calendar' => $block->content ?: 'Calendar event',
                default => ucfirst(str_replace('_', ' ', $block->type)),
            };
        @endphp
        <div class="event-search-result cursor-pointer space-y-2 rounded-2xl transition hover:ring-2 hover:ring-indigo-300" data-search-result-open data-result-title="{{ $resultTitle }}" data-result-date="{{ $block->dailyLog->log_date->format('l, F j, Y') }}" data-result-time="{{ auth()->user()->formatTime($occurredAt) }}" data-result-date-url="{{ route('logs.show', $block->dailyLog->log_date->toDateString()) }}" tabindex="0" role="button" aria-label="Open {{ $resultTitle }} details">
            <header class="flex flex-wrap items-center gap-2 px-1"><time class="font-semibold" datetime="{{ $block->dailyLog->log_date->toDateString() }}">{{ $block->dailyLog->log_date->format('l, F j, Y') }}</time><span class="text-sm text-slate-500">{{ auth()->user()->formatTime($occurredAt) }}</span><a class="btn-secondary ml-auto" href="{{ route('logs.show', $block->dailyLog->log_date->toDateString()) }}">Open date</a></header>
            @include('logs.partials.block', ['block' => $block])
            @if($block->search_details)<script type="application/json" data-search-result-details>{!! json_encode($block->search_details, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>@endif
        </div>
    @empty
        <div id="log-search-empty" class="panel py-12 text-center"><span class="text-4xl" aria-hidden="true">🔎</span><h2 class="mt-3 text-lg font-bold">No matching logs</h2><p class="mt-1 text-sm text-slate-500">Try another word or a shorter phrase.</p></div>
    @endforelse
    @if($results->hasPages())<div id="log-search-pagination">{{ $results->links() }}</div>@endif
@elseif($keyword !== '')
    <div id="log-search-short-query" class="panel py-12 text-center"><span class="text-4xl" aria-hidden="true">⌨️</span><h2 class="mt-3 text-lg font-bold">Type at least two letters</h2><p class="mt-1 text-sm text-slate-500">Results will appear automatically.</p></div>
@else
    <div id="log-search-prompt" class="panel py-12 text-center"><span class="text-4xl" aria-hidden="true">🔍</span><h2 class="mt-3 text-lg font-bold">Find anything you recorded</h2><p class="mt-1 text-sm text-slate-500">Searches entry text, events, GitHub details, calendar metadata, browsing domains, apps, and attachment names.</p></div>
@endif
