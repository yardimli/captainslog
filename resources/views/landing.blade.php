<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Total Log · Your days with context</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>(()=>{const themes=['light','paper','blue','red','dark'],saved=localStorage.getItem('totallog.theme'),theme=themes.includes(saved)?saved:(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light');document.documentElement.dataset.theme=theme;document.documentElement.classList.toggle('dark',theme==='dark'||theme==='red')})()</script>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <nav class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('demo.index') }}" class="flex items-center gap-2 font-black">@include('partials.logo', ['class' => 'h-9 w-9'])<span>Total Log</span></a>
            <span class="hidden rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 sm:inline">See your whole day</span>
            <div id="guest-navigation-actions" class="ml-auto flex items-center gap-1">
                @include('partials.theme-selector')
                @auth
                    <a class="nav-link" href="{{ route('calendar') }}" aria-label="Open calendar">Open calendar</a>
                @else
                    <a class="nav-link" href="{{ route('login') }}" aria-label="Sign in">Sign in</a>
                    <a class="btn" href="{{ route('register') }}" aria-label="Register">Create account</a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="relative overflow-hidden border-b border-slate-200 bg-slate-950 text-white dark:border-slate-800">
            <div id="landing-hero-backdrop" class="absolute inset-0 opacity-40" style="background:radial-gradient(circle at 80% 20%,#4f46e5 0,transparent 28%),radial-gradient(circle at 15% 80%,#0f766e 0,transparent 25%)"></div>
            <div id="landing-hero-content" class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_.8fr] lg:px-8 lg:py-24">
                <div id="landing-hero-copy" class="min-w-0">
                    <p class="text-sm font-bold uppercase tracking-[.24em] text-indigo-300">Your days, kept with context</p>
                    <h1 class="mt-4 max-w-3xl break-words text-5xl font-black leading-[.95] sm:text-7xl">A total log of ordinary days.</h1>
                    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">Bring notes, recurring events, calendar appointments, reading, browsing, desktop activity, images, and GitHub work into one calm timeline.</p>
                    <div id="landing-hero-actions" class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ route('demo.enter') }}" class="btn">Open the read-only demo</a>
                        <a href="#connected-sources" class="btn-secondary border-white/20 bg-white/10 text-white hover:bg-white/20">See connected sources</a>
                    </div>
                </div>
                <aside class="min-w-0 self-end overflow-hidden rounded-3xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-300">Full application demo</p>
                    <h2 class="mt-2 text-2xl font-black">Browse the real calendar and daily logs.</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300">The shared demo includes varied entries, sensor details, images, and GitHub commits. It is read-only, so exploring never changes its data.</p>
                    <ul class="mt-6 space-y-3 text-sm text-slate-200"><li>✓ Week and month calendar views</li><li>✓ Detailed daily timelines and search</li><li>✓ Realistic automatic and manual activity</li></ul>
                </aside>
            </div>
        </section>

        <section id="demo" class="mx-auto max-w-7xl scroll-mt-6 px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
            <div id="demo-launch-card" class="panel grid items-center gap-8 overflow-hidden border-indigo-200 bg-gradient-to-br from-indigo-50 to-white dark:border-indigo-900 dark:from-indigo-950/60 dark:to-slate-900 lg:grid-cols-[1fr_auto]">
                <div id="demo-launch-copy">
                    <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-600">Live simulation</p>
                    <h2 class="mt-2 text-3xl font-black">Step into a complete sample account.</h2>
                    <p class="mt-4 max-w-3xl leading-relaxed text-slate-600 dark:text-slate-300">Open the real Total Log calendar, inspect browsing domains and app usage, preview imagery, and open commit details. The shared demo cannot save edits.</p>
                    <p class="mt-3 text-sm text-slate-500">An administrator can reset the sample, rebuilding its timeline around the current day.</p>
                </div>
                <a href="{{ route('demo.enter') }}" class="btn justify-center px-6 py-3">Explore demo calendar →</a>
            </div>
        </section>

        <section id="connected-sources" class="mx-auto max-w-7xl scroll-mt-6 px-4 pb-14 sm:px-6 lg:px-8 lg:pb-20">
            <div id="connected-sources-heading" class="max-w-3xl"><p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600">Connected sources</p><h2 class="mt-2 text-3xl font-black sm:text-4xl">Your day, without reconstructing it from memory.</h2><p class="mt-4 text-lg leading-relaxed text-slate-500">Combine entries you write with small, privacy-conscious signals from tools you already use.</p></div>
            <div id="connected-source-grid" class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <article class="panel"><span class="text-3xl">🌐</span><h3 class="mt-3 text-xl font-black">Browsing</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">See domain-level desktop time and synchronized mobile visit counts without storing page paths.</p></article>
                <article class="panel"><span class="text-3xl">🖥️</span><h3 class="mt-3 text-xl font-black">Desktop activity</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">Group foreground application activity into useful totals throughout the day.</p></article>
                <article class="panel"><span class="text-3xl">💻</span><h3 class="mt-3 text-xl font-black">GitHub commits</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">Connect project commits to their actual dates and inspect commit messages.</p></article>
                <article class="panel"><span class="text-3xl">📅</span><h3 class="mt-3 text-xl font-black">Calendar</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">Bring read-only appointments into the correct daily log and calendar view.</p></article>
                <article class="panel"><span class="text-3xl">📖</span><h3 class="mt-3 text-xl font-black">Reading</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">Record useful book context without turning reading into another analytics dashboard.</p></article>
                <article class="panel"><span class="text-3xl">✅</span><h3 class="mt-3 text-xl font-black">Events and notes</h3><p class="mt-2 text-sm leading-relaxed text-slate-500">Combine recurring goals, quick events, longer notes, and visual memories.</p></article>
            </div>
        </section>
    </main>
</body>
</html>
