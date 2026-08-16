<nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('calendar') }}" class="flex items-center gap-2 font-bold"><x-application-logo class="h-9 w-9" /><span class="hidden sm:inline">Captain's Log</span></a>
        <div class="ml-auto flex items-center gap-1 text-sm">
            <a class="nav-link {{ request()->routeIs('calendar','logs.*') ? 'nav-active' : '' }}" href="{{ route('calendar') }}">Calendar</a>
            <a class="nav-link {{ request()->routeIs('tasks.*') ? 'nav-active' : '' }}" href="{{ route('tasks.index') }}">Tasks</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'nav-active' : '' }}" href="{{ route('settings.edit') }}">Settings</a>
            <button type="button" data-theme-toggle class="nav-link" aria-label="Toggle dark mode">◐</button>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link" title="Sign out">Sign out</button></form>
        </div>
    </div>
</nav>
