<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? trim(strip_tags((string) $title)).' - ' : '' }}{{ config('app.name', "Captain's Log") }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>if(localStorage.getItem('captainslog.theme')==='dark'||(!localStorage.getItem('captainslog.theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <div class="grid min-h-screen lg:grid-cols-[minmax(22rem,0.85fr)_minmax(32rem,1.15fr)]">
        <aside class="relative hidden overflow-hidden bg-slate-950 text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
            <div class="absolute inset-0 opacity-70" style="background:radial-gradient(circle at 18% 22%,#4f46e5 0,transparent 26%),radial-gradient(circle at 82% 80%,#0f766e 0,transparent 25%)"></div>
            <div class="absolute inset-0 opacity-[.08]" style="background-image:linear-gradient(rgba(255,255,255,.8) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.8) 1px,transparent 1px);background-size:42px 42px"></div>
            <div class="relative">
                <a href="{{ route('demo.index') }}" class="inline-flex items-center gap-3 text-xl font-black">
                    <x-application-logo class="h-12 w-12 shadow-lg shadow-indigo-500/30" />
                    <span>Captain's Log</span>
                </a>
                <div class="mt-20 max-w-xl">
                    <p class="text-xs font-bold uppercase tracking-[.24em] text-indigo-300">Your days, kept with context</p>
                    <h2 class="mt-4 text-5xl font-black leading-[.98]">Return to the bridge.</h2>
                    <p class="mt-6 max-w-lg text-lg leading-relaxed text-slate-300">Your notes, recurring events, recordings, and reflections stay together in one calm, chronological workspace.</p>
                </div>
            </div>
            <div class="relative grid gap-3 text-sm text-slate-300 xl:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Refreshable</strong><span>Every date has a stable URL.</span></div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Private media</strong><span>Owned files stay protected.</span></div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><strong class="block text-white">Day by day</strong><span>A timeline built for real life.</span></div>
            </div>
        </aside>

        <div class="relative flex min-h-screen flex-col">
            <header class="flex h-16 items-center gap-3 border-b border-slate-200 bg-white/80 px-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/80 sm:px-8">
                <a href="{{ route('demo.index') }}" class="flex items-center gap-2 font-black lg:hidden">
                    <x-application-logo class="h-9 w-9" />
                    <span>Captain's Log</span>
                </a>
                <a href="{{ route('demo.index') }}#demo" class="ml-auto text-sm font-semibold text-slate-500 hover:text-indigo-600 dark:text-slate-300 dark:hover:text-indigo-400">Try the live demo</a>
                <a href="{{ route('login') }}" class="nav-link p-2" aria-label="Sign in" title="Sign in"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 7l5 5-5 5M19 12H7M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5"/></svg></a>
                <a href="{{ route('register') }}" class="nav-link p-2" aria-label="Register" title="Register"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8 11a4 4 0 1 0 0-8M19 8v6M22 11h-6"/></svg></a>
                <button type="button" data-theme-toggle class="nav-link p-2" aria-label="Toggle theme" title="Toggle theme"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 1 0 9 9 9 9 0 1 1-9-9Z"/></svg></button>
            </header>

            <main class="flex flex-1 items-center justify-center p-4 sm:p-8 lg:p-12">
                <div class="w-full max-w-lg">
                    <div class="mb-6">
                        @isset($eyebrow)<p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600 dark:text-indigo-400">{{ $eyebrow }}</p>@endisset
                        @isset($title)<h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">{{ $title }}</h1>@endisset
                        @isset($subtitle)<p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endisset
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/50 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20 sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
