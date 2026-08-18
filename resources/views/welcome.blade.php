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
    @auth
        @include('layouts.navigation')
    @else
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
        <nav class="mx-auto flex h-16 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('demo.index') }}" class="flex items-center gap-2 font-black">@include('partials.logo', ['class' => 'h-9 w-9'])<span>Captain's Log</span></a>
            <span class="hidden rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200 sm:inline">Live guest simulation</span>
            <div id="guest-navigation-actions" class="ml-auto flex items-center gap-1">
                <button type="button" data-theme-toggle class="nav-link p-2" aria-label="Toggle theme" title="Toggle theme"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a6 6 0 1 0 9 9 9 9 0 1 1-9-9Z"/></svg></button>
                @auth<a class="nav-link p-2" href="{{ route('calendar') }}" aria-label="Open my log" title="Open my log"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18v16H3zM16 3v4M8 3v4M3 10h18"/></svg></a>@else<a class="nav-link p-2" href="{{ route('login') }}" aria-label="Sign in" title="Sign in"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 7l5 5-5 5M19 12H7M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5"/></svg></a><a class="nav-link p-2" href="{{ route('register') }}" aria-label="Register" title="Register"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M8 11a4 4 0 1 0 0-8M19 8v6M22 11h-6"/></svg></a>@endauth
            </div>
        </nav>
    </header>
    @endauth

    <main>
        <section class="relative overflow-hidden border-b border-slate-200 bg-slate-950 text-white dark:border-slate-800">
            <div id="landing-hero-backdrop" class="absolute inset-0 opacity-40" style="background:radial-gradient(circle at 80% 20%,#4f46e5 0,transparent 28%),radial-gradient(circle at 15% 80%,#0f766e 0,transparent 25%)"></div>
            <div id="landing-hero-content" class="relative mx-auto grid max-w-7xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.2fr_.8fr] lg:px-8 lg:py-24">
                <div id="landing-hero-copy" class="min-w-0"><p class="text-sm font-bold uppercase tracking-[.24em] text-indigo-300">Your days, kept with context</p><h1 class="mt-4 max-w-3xl break-words text-5xl font-black leading-[.95] sm:text-7xl">A captain's log for ordinary missions.</h1><p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">Capture notes, recurring events, media, recordings, and AI-assisted reflections in one calm daily timeline. Try the real interface below—no signup required.</p><a href="#demo" class="btn mt-8">Enter the simulation ↓</a></div>
                <div id="landing-demo-summary" class="min-w-0 self-end overflow-hidden rounded-3xl border border-white/10 bg-white/10 p-6 shadow-2xl backdrop-blur"><div id="landing-demo-count" class="flex min-w-0 items-center gap-3"><span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-emerald-400 font-black text-slate-950">8</span><div id="landing-demo-count-copy" class="min-w-0"><p class="break-words font-bold">Eight days already aboard</p><p class="break-words text-sm text-slate-300">The previous seven days plus today</p></div></div><div id="landing-demo-preview-images" class="mt-5 grid grid-cols-2 gap-2"><img src="{{ asset('images/demo-yoga-observation-deck.png') }}" alt="Yoga session on a fictional observation deck" class="aspect-[3/2] w-full rounded-xl object-cover"><img src="{{ asset('images/demo-pet-medication.png') }}" alt="Three pets during a fictional medication briefing" class="aspect-[3/2] w-full rounded-xl object-cover"></div><div id="landing-demo-features" class="mt-6 min-w-0 space-y-3 text-sm text-slate-300"><p class="break-words">✓ Emoji-aware notes, events, chats, and images</p><p class="break-words">✓ A private guest workspace for this browser</p><p class="break-words">✓ Refreshable dates and persistent counters</p></div></div>
            </div>
        </section>

        <section id="demo" class="mx-auto max-w-7xl scroll-mt-4 space-y-5 p-4 sm:p-6 lg:p-8">
            <div id="demo-section-heading" class="flex min-w-0 flex-wrap items-end gap-3"><div id="demo-section-heading-copy" class="min-w-0"><p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-600">Live simulation</p><h2 class="break-words text-2xl font-black sm:text-3xl">USS Inner Peace</h2></div><div id="demo-privacy-message" class="min-w-0 max-w-md rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200 sm:ml-auto"><strong>Your private demo:</strong> an opaque cookie reconnects this browser to its own guest account. Other visitors cannot see these entries.</div></div>

            <div id="demo-date-navigation" class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                @foreach($days as $calendarDay)
                    @php $calendarLog = $logs->get($calendarDay->toDateString()); @endphp
                    <a href="{{ route('demo.index', ['date' => $calendarDay->toDateString()]) }}#demo" class="rounded-2xl border p-3 text-center transition hover:-translate-y-0.5 hover:border-indigo-400 {{ $calendarDay->isSameDay($day) ? 'border-indigo-500 bg-indigo-600 text-white shadow-lg shadow-indigo-500/20' : 'border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                        <span class="block text-[10px] font-bold uppercase opacity-70">{{ $calendarDay->isToday() ? 'Today' : $calendarDay->format('D') }}</span><span class="mt-1 block text-xl font-black">{{ $calendarDay->day }}</span><span class="mt-1 block text-[10px] opacity-70">{{ $calendarLog?->blocks_count ?? 0 }} entries</span>
                    </a>
                @endforeach
            </div>

            <div id="demo-workspace-layout" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_21rem]">
                <div id="demo-log-column" class="min-w-0 space-y-4">
                    <section class="panel overflow-hidden bg-gradient-to-br from-indigo-600 to-violet-700 text-white dark:border-indigo-500"><div id="demo-log-heading" class="flex flex-wrap items-center gap-4"><div id="demo-log-heading-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-200">Captain's log</p><h3 class="text-2xl font-black">{{ $day->format('l, F j, Y') }}</h3></div><div id="demo-event-buttons" class="ml-auto flex flex-wrap gap-2">@foreach($tasks as $task)<button class="rounded-xl px-3 py-2 text-sm font-bold shadow-sm hover:brightness-110" style="background-color:{{ $task->color_hex }};color:{{ $task->button_text_color }}" data-task-event="{{ route('demo.events.store', [$log, $task]) }}" data-name="{{ $task->name }}" data-options='@json($task->options ?? [])'><span class="mr-1 inline-block h-3 w-3 rounded-sm border border-current opacity-80" style="background-color:{{ $task->color_hex }}"></span><span class="mr-1 text-lg" aria-hidden="true">{{ $task->emoji }}</span>{{ $task->name }} <span class="ml-1 rounded-full bg-white/20 px-2" data-count>{{ $counts[$task->id] ?? 0 }}</span></button>@endforeach</div></div></section>

                    @foreach($log->blocks as $block)
                        @php $recordedAt = $block->taskEvent?->occurred_at ?? $block->occurred_at ?? $block->created_at; $blockId = $block->id; @endphp
                        <div id="demo-block-row-{{ $blockId }}" class="timeline-item flex min-w-0 {{ $block->type === 'event' ? '' : 'cursor-pointer' }} items-start gap-3" @unless($block->type === 'event') data-timeline-edit data-timeline-time="{{ $recordedAt->format('H:i') }}" data-edit-kind="block" data-edit-url="{{ route('demo.blocks.update', $block) }}" data-edit-content="{{ $block->content }}" data-edit-emoji="{{ $block->emoji }}" data-edit-updated="{{ $block->updated_at->format('g:i A') }}" data-edit-media="{{ $block->attachments->where('type', 'image')->map(fn ($attachment) => ['url' => route('demo.attachments.show', $attachment)])->values()->toJson() }}" data-delete-url="{{ route('demo.blocks.destroy', $block) }}" data-has-media="{{ $block->attachments->isNotEmpty() ? 'true' : 'false' }}" data-block-id="{{ $blockId }}" @endunless>
                            <time class="w-20 shrink-0 pt-4 text-center font-mono text-xs font-bold text-slate-500">{{ $recordedAt->format('g:i A') }}</time>
                            <article class="panel group min-w-0 flex-1">
                                <div class="demo-block-content whitespace-pre-wrap text-[1.02rem] leading-relaxed"><span class="mr-2 inline-block align-middle text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="mr-2 inline-flex align-middle rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str_replace('_',' ', $block->type) }}</span>@if($block->taskEvent)<strong class="text-lg">{{ $block->taskEvent->task_name }} <span class="text-indigo-600">· {{ $block->taskEvent->selected_value }}</span></strong>@endif @if($block->content){{ $block->content }}@endif</div>
                                @foreach($block->attachments->where('type', 'image') as $attachment)
                                    <div class="block-image-attachment group/image relative mt-4 h-[512px] max-h-[512px] overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-950">
                                        <img src="{{ route('demo.attachments.show', $attachment) }}" alt="Image attached to this demo log entry" class="h-[512px] max-h-[512px] w-full object-contain" loading="lazy">
                                        <div class="block-image-controls absolute right-3 top-3 flex gap-2 opacity-100 transition sm:opacity-0 sm:group-hover/image:opacity-100 sm:focus-within:opacity-100">
                                            <button type="button" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-950/80 text-white shadow-lg backdrop-blur hover:bg-indigo-600" data-image-preview-open data-image-url="{{ route('demo.attachments.show', $attachment) }}" data-image-name="{{ $attachment->original_name }}" aria-label="Open image" title="Open image"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h6v6M14 10l7-7M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg></button>
                                            <a href="{{ route('demo.attachments.show', $attachment) }}" download="{{ $attachment->original_name }}" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-950/80 text-white shadow-lg backdrop-blur hover:bg-indigo-600" aria-label="Download image" title="Download image"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg></a>
                                        </div>
                                    </div>
                                @endforeach
                            </article>
                        </div>
                    @endforeach
                </div>

                <aside class="space-y-4">
                    <section class="panel lg:sticky lg:top-4"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Try the real thing</p><h3 class="mt-1 text-xl font-black">Add to this day</h3><p class="mt-1 text-sm text-slate-500">Choose an emoji and time in the same side editor used by the signed-in app.</p><button type="button" class="btn mt-4 w-full" data-composer-open data-default-time="{{ $day->isToday() ? now()->format('H:i') : '12:00' }}">Write a demo note</button><div id="demo-feature-list" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800"><p class="text-sm font-bold">Now demonstrated</p><ul class="mt-2 space-y-2 text-sm text-slate-500"><li>😀 Searchable emoji categories</li><li>🖼️ Image attachments and previews</li><li>🕐 Visual time selection</li><li>✏️ Close-to-save side editing</li></ul></div><div id="demo-crew-list" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800"><p class="text-sm font-bold">The crew</p><ul class="mt-2 space-y-2 text-sm text-slate-500"><li>🐕 Worf · beagle, medication</li><li>🐕 T'Paw · terrier, medication</li><li>🐈 Spot · cat, command disputes</li></ul></div></section>
                </aside>
            </div>
        </section>

        <div id="log-composer-overlay" class="fixed inset-0 hidden" style="z-index:80" data-overlay="composer" role="dialog" aria-modal="true" aria-label="Edit this demo log">
            <button type="button" class="absolute inset-0 bg-slate-950/55 opacity-0 transition-opacity" data-overlay-backdrop data-overlay-close="composer" aria-label="Close log composer"></button>
            <aside class="absolute inset-y-0 right-0 w-full max-w-md translate-x-full overflow-y-auto bg-slate-100 p-4 shadow-2xl transition-transform duration-300 dark:bg-slate-950 sm:p-6" data-overlay-panel>
                <div id="demo-composer-heading" class="mb-4 flex items-start gap-2"><div id="demo-composer-heading-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Private guest workspace</p><h2 class="text-2xl font-black" data-composer-title>Add to this log</h2><p class="mt-1 hidden text-xs text-slate-500" data-composer-updated></p></div><div id="demo-composer-heading-actions" class="ml-auto flex items-center gap-2"><button type="button" class="btn-secondary hidden border-rose-300 text-rose-700" data-composer-cancel>Cancel</button><button type="button" class="btn-secondary" data-overlay-close="composer">Close</button></div></div>
                <label class="label">Entry time</label><div id="demo-composer-time-picker" class="mb-4" data-time-picker><button type="button" class="btn-secondary w-full justify-center text-lg font-bold" data-time-picker-open></button><input type="hidden" name="composer_time" required data-composer-time data-time-picker-input value="{{ $day->isToday() ? now()->format('H:i') : '12:00' }}"></div>
                <section class="panel"><h2 class="mb-1 text-lg font-bold" data-note-heading>Write a note</h2><p class="mb-3 text-sm text-slate-500">Emoji, time, and text are stored in this browser's private guest account.</p><div id="demo-composer-existing-media" class="mb-3 hidden grid-cols-2 gap-2" data-composer-existing-media></div><form data-ajax data-composer-note-form method="POST" action="{{ route('demo.blocks.store', $log) }}" data-create-action="{{ route('demo.blocks.store', $log) }}" class="space-y-3">@csrf<input type="hidden" name="type" value="text"><input type="hidden" name="block_id" data-composer-block-field><input type="hidden" name="occurred_at" data-composer-time-field>@include('partials.emoji-picker', ['pickerId' => 'demo-composer-entry-emoji', 'name' => 'emoji', 'value' => '📝', 'label' => 'Entry emoji'])<textarea class="input" name="content" rows="7" placeholder="Captain's log, supplemental…" required data-composer-content></textarea><p class="hidden text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-autosave-status role="status" aria-live="polite"></p><button class="btn w-full" data-composer-submit>Add demo entry</button></form></section>
                <div id="composer-entry-actions" class="mt-6 hidden grid-cols-1 gap-2 border-t border-slate-200 pt-4 dark:border-slate-800" data-composer-entry-actions><button type="button" class="btn-secondary text-rose-600" data-composer-delete>Delete</button></div>
            </aside>
        </div>
    </main>
    @include('partials.javascript-templates')
</body>
</html>
