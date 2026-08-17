@extends('layouts.app')

@section('content')
    <div id="calendar-page-container" class="mx-auto max-w-7xl space-y-4 p-4 sm:p-6 lg:p-8">
        <div id="calendar-navigation-controls" class="panel flex flex-wrap items-center gap-2">
            @php $jump = $view === 'month' ? 'month' : ($view === 'week' ? 'week' : 'day'); @endphp
            <a class="btn-secondary" href="{{ route('calendar', $focus->copy()->sub(1, $jump)->toDateString()) }}?view={{ $view }}">← Previous</a>
            <a class="btn-secondary" href="{{ route('calendar', now()->toDateString()) }}?view={{ $view }}">Today</a>
            <a class="btn-secondary" href="{{ route('calendar', $focus->copy()->add(1, $jump)->toDateString()) }}?view={{ $view }}">Next →</a>
            <label class="ml-auto flex items-center gap-2 text-sm">View
                <select data-calendar-view data-day-url="{{ route('logs.show', $focus->toDateString()) }}" class="rounded-xl border-slate-300 bg-white py-2 text-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach(['day','week','month'] as $mode)<option value="{{ $mode }}" @selected($view===$mode)>{{ ucfirst($mode) }}</option>@endforeach
                </select>
            </label>
        </div>
        <div id="calendar-day-grid" class="grid {{ $view === 'day' ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-7' }} gap-2">
            @foreach($days as $day)
                @php $item = $logs->get($day->toDateString()); @endphp
                <a href="{{ route('logs.show', $day->toDateString()) }}" class="group min-h-32 rounded-2xl border p-3 transition hover:-translate-y-0.5 hover:border-indigo-400 hover:shadow-md {{ $day->isToday() ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/50' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }} {{ $view === 'month' && !$day->isSameMonth($focus) ? 'opacity-50' : '' }}">
                    <div class="calendar-day-heading flex items-center justify-between"><span class="text-xs font-semibold uppercase text-slate-500">{{ $day->format('D') }}</span><span class="grid h-8 w-8 place-items-center rounded-full {{ $day->isToday() ? 'bg-indigo-600 text-white' : '' }}">{{ $day->day }}</span></div>
                    <div class="calendar-day-summary mt-5 space-y-1 text-xs text-slate-500">
                        @if($item)<p>{{ $item->blocks_count }} log {{ Str::plural('block', $item->blocks_count) }}</p><p>{{ $item->attachments_count }} {{ Str::plural('attachment', $item->attachments_count) }}</p>@else<p class="opacity-0 transition group-hover:opacity-100">Open log →</p>@endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endsection
