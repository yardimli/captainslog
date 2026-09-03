@php
    $iconClass = 'h-5 w-5';
    $activeLogDate = request()->routeIs('logs.show') ? request()->route('date') : today()->toDateString();
@endphp
<nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95" data-primary-navigation>
    <div id="primary-navigation-content" class="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center gap-2 px-4 py-2 sm:flex-nowrap sm:px-6 lg:px-8">
        <a href="{{ route('calendar') }}" class="flex min-w-0 items-center gap-2 font-bold">
            @include('partials.logo', ['class' => 'h-9 w-9 shrink-0'])
            <span class="min-w-0 leading-tight">
                <span class="block">Total Log</span>
                @if(request()->routeIs('logs.show') && isset($day))
                    <time class="block whitespace-nowrap text-[11px] font-medium text-slate-500 dark:text-slate-400 sm:text-xs" datetime="{{ $day->toDateString() }}" data-navigation-date>{{ $day->format('l, F j, Y') }}</time>
                @elseif(request()->routeIs('calendar') && isset($start, $end))
                    <span class="block whitespace-nowrap text-[11px] font-medium text-slate-500 dark:text-slate-400 sm:text-xs" data-navigation-date>{{ $start->format('M j') }} &ndash; {{ $end->format('M j, Y') }}</span>
                @endif
            </span>
        </a>

        @if(request()->routeIs('logs.show') && isset($day, $tasks, $log, $counts))
            <div id="daily-log-navigation-actions" class="order-3 flex w-full items-center justify-end gap-1 sm:order-none sm:w-auto" data-day-navigation>
                <a class="nav-link grid h-9 w-9 place-items-center p-0" href="{{ route('logs.show', $day->copy()->subDay()->toDateString()) }}" aria-label="Previous day" title="Previous day">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                </a>
                <a class="nav-link grid h-9 w-9 place-items-center p-0" href="{{ route('logs.show', today()->toDateString()) }}" aria-label="Today" title="Today">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><circle cx="12" cy="15" r="2" fill="currentColor" stroke="none"/></svg>
                </a>
                <a class="nav-link grid h-9 w-9 place-items-center p-0" href="{{ route('logs.show', $day->copy()->addDay()->toDateString()) }}" aria-label="Next day" title="Next day">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a class="nav-link grid h-9 w-9 place-items-center p-0" href="{{ route('calendar') }}" aria-label="Open calendar" title="Calendar">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18M8 14h2M12 14h2M16 14h2M8 18h2M12 18h2"/></svg>
                </a>
                    <details class="relative z-50 {{ $tasks->isEmpty() ? 'hidden' : '' }}" data-events-menu>
                        <summary class="nav-link grid h-9 w-9 cursor-pointer list-none place-items-center p-0 [&::-webkit-details-marker]:hidden" aria-label="Events" title="Events">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m4 6 2 2 3-3M11 7h9M4 12l2 2 3-3M11 13h9M4 18l2 2 3-3M11 19h9"/></svg>
                        </summary>
                        <div id="more-events-menu" class="absolute right-0 mt-2 grid w-72 gap-1 rounded-xl border bg-white p-2 shadow-2xl dark:border-slate-700 dark:bg-slate-900" style="z-index:70">
                            @foreach($tasks as $task)
                                <button class="flex items-center rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" data-task-event="{{ route('events.store', [$log, $task]) }}" data-capture-location data-name="{{ $task->name }}" data-options='@json($task->options ?? [])'><span class="mr-2 h-4 w-4 shrink-0 rounded-sm border border-slate-300 dark:border-slate-600" style="background-color:{{ $task->color_hex }}"></span><span class="mr-2 text-lg" aria-hidden="true">{{ $task->emoji }}</span><span class="min-w-0"><strong>{{ $task->name }}</strong> <span class="rounded-full bg-slate-100 px-1.5 dark:bg-slate-800" data-count>{{ $counts[$task->id] ?? 0 }}</span><small class="block text-slate-500">{{ collect($task->scheduled_times ?? [])->map(fn ($time) => auth()->user()->formatClock($time))->implode(', ') ?: 'Any time' }}</small></span></button>
                            @endforeach
                        </div>
                    </details>
            </div>
        @endif

        @if(request()->routeIs('notes.*'))
            <a class="nav-link ml-auto grid h-9 w-9 place-items-center p-0" href="{{ route('logs.show', today()->toDateString()) }}" aria-label="Open today's log" title="Today's log">
                <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/><path d="M8 7h8M8 11h6"/></svg>
            </a>
        @else
            <a class="nav-link ml-auto grid h-9 w-9 place-items-center p-0" href="{{ route('notes.index') }}" aria-label="Open notes" title="Notes">
                <svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h9l3 3v15H6z"/><path d="M14 3v4h4M9 11h6M9 15h6"/></svg>
            </a>
        @endif
        @include('partials.theme-selector')
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link p-2" aria-label="Sign out" title="Sign out"><svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></button></form>
        <button type="button" class="nav-link p-2" data-mobile-nav-toggle aria-expanded="false" aria-controls="account-navigation" aria-label="Open navigation" title="Menu"><svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>

    <div id="account-navigation" class="absolute right-4 top-14 hidden w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-800 dark:bg-slate-900 sm:right-6 lg:right-8" data-mobile-nav-menu>
        <div id="account-navigation-links" class="grid gap-1 text-sm">
            <a class="nav-link {{ request()->routeIs('notes.*') ? 'nav-active' : '' }}" href="{{ route('notes.index') }}">Notes</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'nav-active' : '' }}" href="{{ route('settings.edit') }}">API settings</a>
            <a class="nav-link {{ request()->routeIs('tasks.*') ? 'nav-active' : '' }}" href="{{ route('tasks.index') }}">Event setup</a>
            <a class="nav-link {{ request()->routeIs('sensors.*') ? 'nav-active' : '' }}" href="{{ route('sensors.index') }}">Sensors</a>
            <a class="nav-link {{ request()->routeIs('api-usage.*') ? 'nav-active' : '' }}" href="{{ route('api-usage.index') }}">API usage</a>
            @if(auth()->user()->is_admin)<a class="nav-link {{ request()->routeIs('admin.*') ? 'nav-active' : '' }}" href="{{ route('admin.users') }}">Admin</a>@endif
            <a class="nav-link {{ request()->routeIs('profile.*') ? 'nav-active' : '' }}" href="{{ route('profile.edit') }}">Account settings</a>
            @if(request()->routeIs('logs.show') && request()->boolean('show_hidden'))
                <a class="nav-link font-semibold text-amber-700 dark:text-amber-300" href="{{ route('logs.show', $activeLogDate) }}" data-hidden-entries-toggle>Hide hidden entries</a>
            @else
                <a class="nav-link font-semibold text-amber-700 dark:text-amber-300" href="{{ route('logs.show', $activeLogDate) }}?show_hidden=1" data-hidden-entries-toggle>Show hidden entries</a>
            @endif
            <div class="navigation-divider my-1 border-t border-slate-200 dark:border-slate-800"></div>
            @if(request()->routeIs('logs.show'))
                <button type="button" class="nav-link text-left font-semibold text-indigo-600 dark:text-indigo-400" data-panel-open="chat">Chat with log</button>
            @else
                <a class="nav-link font-semibold text-indigo-600 dark:text-indigo-400" href="{{ route('logs.show', $activeLogDate) }}?panel=chat">Chat with log</a>
            @endif
        </div>
    </div>
</nav>
