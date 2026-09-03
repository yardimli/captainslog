<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') - @endif{{ config('app.name', 'Total Record') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>(()=>{const themes=['light','paper','blue','red','dark'],saved=localStorage.getItem('captainslog.theme'),theme=themes.includes(saved)?saved:(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.dataset.theme=theme;document.documentElement.classList.toggle('dark',theme==='dark'||theme==='red')})()</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div id="guest-page-shell" class="grid min-h-screen lg:grid-cols-[minmax(22rem,0.85fr)_minmax(32rem,1.15fr)]">
        <aside class="relative hidden overflow-hidden bg-slate-950 text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            <div id="guest-hero-gradient" class="absolute inset-0 opacity-70" style="background:radial-gradient(circle at 18% 22%,#4f46e5 0,transparent 26%),radial-gradient(circle at 82% 80%,#0f766e 0,transparent 25%)"></div>
            <div id="guest-hero-grid" class="absolute inset-0 opacity-[.08]" style="background-image:linear-gradient(rgba(255,255,255,.8) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.8) 1px,transparent 1px);background-size:42px 42px"></div>
            <div id="guest-hero-content" class="relative">
                <a href="{{ route('demo.index') }}" class="inline-flex items-center gap-3 text-xl font-black">
                    @include('partials.logo', ['class' => 'h-12 w-12 shadow-lg shadow-indigo-500/30'])
                    <span>Total Record</span>
                </a>
                <div id="guest-hero-copy" class="mt-20 max-w-xl">
                    <p class="text-xs font-bold uppercase tracking-[.24em] text-indigo-300">Your days, kept with context</p>
                    <h2 class="mt-4 text-5xl font-black leading-[.98]">Return to the bridge.</h2>
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-slate-300">Your daily entries, recurring events, standalone notes, and reflections stay together in one calm workspace.</p>
                </div>
            </div>
            <div id="guest-capability-grid" class="relative grid gap-3 text-sm text-slate-300 xl:grid-cols-3">
                <div class="guest-capability-card rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Refreshable</strong><span>Every date has a stable URL.</span></div>
                <div class="guest-capability-card rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Private media</strong><span>Owned files stay protected.</span></div>
                <div class="guest-capability-card rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Day by day</strong><span>A timeline built for real life.</span></div>
            </div>
        </aside>

        <div id="guest-form-column" class="relative flex min-h-screen flex-col">
            <header class="flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 sm:px-8">
                <a href="{{ route('demo.index') }}" class="flex items-center gap-2 font-black lg:hidden">
                    @include('partials.logo', ['class' => 'h-9 w-9'])
                    <span>Total Record</span>
                </a>
                <a href="{{ route('demo.index') }}#demo" class="ml-auto text-sm font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400">Try the live demo</a>
                <a href="{{ route('login') }}" class="nav-link p-2" aria-label="Sign in" title="Sign in"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 7l5 5-5 5M19 12H7M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5"/></svg></a>
                <a href="{{ route('register') }}" class="nav-link p-2" aria-label="Register" title="Register"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8 11a4 4 0 1 0 0-8M19 8v6M22 11h-6"/></svg></a>
                @include('partials.theme-selector')
            </header>

            <main class="flex flex-1 items-center justify-center p-4 sm:p-8 lg:p-12">
                <div id="guest-form-container" class="w-full max-w-lg">
                    <div id="guest-form-heading" class="mb-6">
                        @hasSection('eyebrow')<p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600 dark:text-indigo-400">@yield('eyebrow')</p>@endif
                        @hasSection('title')<h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">@yield('title')</h1>@endif
                        @hasSection('subtitle')<p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">@yield('subtitle')</p>@endif
                    </div>
                    <div id="guest-form-panel" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 sm:p-8">
                        @yield('content')
                    </div>
                </div>
            </main>
        </div>
    </div>
    @include('partials.javascript-templates')
</body>
</html>
