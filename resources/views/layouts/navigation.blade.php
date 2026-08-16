@php
    $iconClass = 'h-5 w-5';
    $activeLogDate = request()->routeIs('logs.show') ? request()->route('date') : today()->toDateString();
@endphp
<nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95" data-primary-navigation>
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('calendar') }}" class="flex items-center gap-2 font-bold"><x-application-logo class="h-9 w-9" /><span class="hidden sm:inline">Captain's Log</span></a>
        <button type="button" data-theme-toggle class="nav-link ml-auto p-2" aria-label="Toggle theme" title="Toggle theme"><svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 1 0 9 9 9 9 0 1 1-9-9Z"/></svg></button>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link p-2" aria-label="Sign out" title="Sign out"><svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></button></form>
        <button type="button" class="nav-link p-2" data-mobile-nav-toggle aria-expanded="false" aria-controls="account-navigation" aria-label="Open navigation" title="Menu"><svg class="{{ $iconClass }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
    </div>

    <div id="account-navigation" class="absolute right-4 top-14 hidden w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl dark:border-slate-800 dark:bg-slate-900 sm:right-6 lg:right-8" data-mobile-nav-menu>
        <div class="grid gap-1 text-sm">
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'nav-active' : '' }}" href="{{ route('settings.edit') }}">API settings</a>
            <a class="nav-link {{ request()->routeIs('tasks.*') ? 'nav-active' : '' }}" href="{{ route('tasks.index') }}">Event setup</a>
            <a class="nav-link {{ request()->routeIs('api-usage.*') ? 'nav-active' : '' }}" href="{{ route('api-usage.index') }}">API usage</a>
            <a class="nav-link {{ request()->routeIs('profile.*') ? 'nav-active' : '' }}" href="{{ route('profile.edit') }}">Account settings</a>
            @if(request()->routeIs('logs.show') && request()->boolean('show_hidden'))
                <a class="nav-link font-semibold text-amber-700 dark:text-amber-300" href="{{ route('logs.show', $activeLogDate) }}">Hide hidden entries</a>
            @else
                <a class="nav-link font-semibold text-amber-700 dark:text-amber-300" href="{{ route('logs.show', $activeLogDate) }}?show_hidden=1">Show hidden entries</a>
            @endif
            <div class="my-1 border-t border-slate-200 dark:border-slate-800"></div>
            @if(request()->routeIs('logs.show'))
                <button type="button" class="nav-link text-left font-semibold text-emerald-600 dark:text-emerald-400" data-panel-open="image">Generate image</button>
                <button type="button" class="nav-link text-left font-semibold text-indigo-600 dark:text-indigo-400" data-panel-open="chat">Chat with log</button>
            @else
                <a class="nav-link font-semibold text-emerald-600 dark:text-emerald-400" href="{{ route('logs.show', $activeLogDate) }}?panel=image">Generate image</a>
                <a class="nav-link font-semibold text-indigo-600 dark:text-indigo-400" href="{{ route('logs.show', $activeLogDate) }}?panel=chat">Chat with log</a>
            @endif
        </div>
    </div>
</nav>
