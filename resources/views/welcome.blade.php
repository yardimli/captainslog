<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Captain's Log · Live guest demo</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>if(localStorage.getItem('captainslog.theme')==='dark'||(!localStorage.getItem('captainslog.theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <nav class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('demo.index') }}" class="flex items-center gap-2 font-black"><x-application-logo class="h-9 w-9" /><span>Captain's Log</span></a>
            <span class="hidden rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 sm:inline">Live guest simulation</span>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" data-theme-toggle class="nav-link" aria-label="Toggle dark mode">◐</button>
                @auth<a class="btn" href="{{ route('calendar') }}">Open my log</a>@else<a class="nav-link" href="{{ route('login') }}">Sign in</a><a class="btn" href="{{ route('register') }}">Create account</a>@endauth
            </div>
        </nav>
    </header>

    <main>
        <section class="relative overflow-hidden border-b border-slate-200 bg-slate-950 text-white dark:border-slate-800">
            <div class="absolute inset-0 opacity-40" style="background:radial-gradient(circle at 80% 20%,#4f46e5 0,transparent 28%),radial-gradient(circle at 15% 80%,#0f766e 0,transparent 25%)"></div>
            <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_.8fr] lg:px-8 lg:py-24">
                <div class="min-w-0"><p class="text-sm font-bold uppercase tracking-[.24em] text-indigo-300">Your days, kept with context</p><h1 class="mt-4 max-w-3xl break-words text-5xl font-black leading-[.95] sm:text-7xl">A captain's log for ordinary missions.</h1><p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">Capture notes, recurring events, media, recordings, and AI-assisted reflections in one calm daily timeline. Try the real interface below—no signup required.</p><a href="#demo" class="btn mt-8">Enter the simulation ↓</a></div>
                <div class="min-w-0 self-end overflow-hidden rounded-3xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur"><div class="flex min-w-0 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-emerald-400 font-black text-slate-950">8</span><div class="min-w-0"><p class="break-words font-bold">Eight days already aboard</p><p class="break-words text-sm text-slate-300">The previous seven days plus today</p></div></div><div class="mt-6 min-w-0 space-y-3 text-sm text-slate-300"><p class="break-words">✓ Real database-backed notes and events</p><p class="break-words">✓ A private guest workspace for this browser</p><p class="break-words">✓ Refreshable dates and persistent counters</p></div></div>
            </div>
        </section>

        <section id="demo" class="mx-auto max-w-7xl scroll-mt-4 space-y-5 p-4 sm:p-6 lg:p-8">
            <div class="flex min-w-0 flex-wrap items-end gap-3"><div class="min-w-0"><p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-600">Live simulation</p><h2 class="break-words text-2xl font-black sm:text-3xl">USS Inner Peace</h2></div><div class="min-w-0 max-w-md rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 sm:ml-auto"><strong>Your private demo:</strong> an opaque cookie reconnects this browser to its own guest account. Other visitors cannot see these entries.</div></div>

            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                @foreach($days as $calendarDay)
                    @php $calendarLog = $logs->get($calendarDay->toDateString()); @endphp
                    <a href="{{ route('demo.index', ['date' => $calendarDay->toDateString()]) }}#demo" class="rounded-2xl border p-3 text-center transition hover:-translate-y-0.5 hover:border-indigo-400 {{ $calendarDay->isSameDay($day) ? 'border-indigo-500 bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                        <span class="block text-[10px] font-bold uppercase opacity-70">{{ $calendarDay->isToday() ? 'Today' : $calendarDay->format('D') }}</span><span class="mt-1 block text-xl font-black">{{ $calendarDay->day }}</span><span class="mt-1 block text-[10px] opacity-70">{{ $calendarLog?->blocks_count ?? 0 }} entries</span>
                    </a>
                @endforeach
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div class="min-w-0 space-y-4">
                    <section class="panel overflow-hidden bg-gradient-to-br from-indigo-600 to-violet-700 text-white dark:border-indigo-500"><div class="flex flex-wrap items-center gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-indigo-200">Captain's log</p><h3 class="text-2xl font-black">{{ $day->format('l, F j, Y') }}</h3></div><div class="ml-auto flex flex-wrap gap-2">@foreach($tasks as $task)<button class="rounded-xl px-3 py-2 text-sm font-bold shadow-sm hover:brightness-110" style="background-color:{{ $task->color_hex }};color:{{ $task->button_text_color }}" data-task-event="{{ route('demo.events.store', [$log, $task]) }}" data-name="{{ $task->name }}" data-options='@json($task->options ?? [])'><span class="mr-1 inline-block h-3 w-3 rounded-sm border border-current opacity-80" style="background-color:{{ $task->color_hex }}"></span>{{ $task->name }} <span class="ml-1 rounded-full bg-white/20 px-2" data-count>{{ $counts[$task->id] ?? 0 }}</span></button>@endforeach</div></div></section>

                    @foreach($log->blocks as $block)
                        <article class="panel group">
                            <div class="mb-3 flex items-start gap-3"><span class="rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str_replace('_',' ', $block->type) }}</span><div class="ml-auto text-right text-xs text-slate-400"><time>Created {{ $block->created_at->format('g:i A') }}</time><p>Updated {{ $block->updated_at->format('g:i A') }}</p></div></div>
                            @if($block->taskEvent)<p class="mb-2 text-lg font-black">{{ $block->taskEvent->task_name }} <span class="text-indigo-600">· {{ $block->taskEvent->selected_value }}</span></p>@endif
                            @if($block->content)<div class="whitespace-pre-wrap text-[1.02rem] leading-relaxed">{{ $block->content }}</div>@endif
                            @unless($block->type === 'event')<div class="mt-4 flex gap-4 border-t border-slate-100 pt-3 text-xs dark:border-slate-800"><button class="font-bold text-indigo-600" data-edit-block="{{ route('demo.blocks.update', $block) }}" data-content="{{ e($block->content) }}">Edit</button><button class="font-bold text-rose-600" data-delete="{{ route('demo.blocks.destroy', $block) }}">Delete</button></div>@endunless
                        </article>
                    @endforeach
                </div>

                <aside class="space-y-4">
                    <section class="panel lg:sticky lg:top-4"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Try the real thing</p><h3 class="mt-1 text-xl font-black">Add to this day</h3><p class="mt-1 text-sm text-slate-500">This saves to your browser's guest account and survives refreshes.</p><form data-ajax method="POST" action="{{ route('demo.blocks.store', $log) }}" class="mt-4 space-y-3">@csrf<textarea class="input" name="content" rows="6" placeholder="Captain's log, supplemental…" required></textarea><button class="btn w-full">Add demo entry</button></form><div class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800"><p class="text-sm font-bold">The crew</p><ul class="mt-2 space-y-2 text-sm text-slate-500"><li>🐕 Worf · beagle, medication</li><li>🐕 T'Paw · terrier, medication</li><li>🐈 Spot · cat, command disputes</li></ul></div></section>
                </aside>
            </div>
        </section>
    </main>
</body>
</html>
