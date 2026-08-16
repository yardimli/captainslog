<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center gap-3"><div><p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Daily log</p><h1 class="text-2xl font-bold">{{ $day->format('l, F j, Y') }}</h1></div><div class="ml-auto flex flex-wrap items-center gap-2"><a class="btn-secondary" href="{{ route('logs.show', $day->copy()->subDay()->toDateString()) }}" aria-label="Previous day">← Previous</a><a class="btn-secondary" href="{{ route('logs.show', today()->toDateString()) }}">Today</a><a class="btn-secondary" href="{{ route('logs.show', $day->copy()->addDay()->toDateString()) }}" aria-label="Next day">Next →</a><a class="btn-secondary" href="{{ route('calendar', $day->toDateString()) }}?view=week">Calendar</a></div></div></x-slot>
    <div class="mx-auto grid max-w-7xl gap-5 p-4 sm:p-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:p-8">
        <div class="min-w-0 space-y-5">
            <section class="panel">
                <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Quick events</h2><a href="{{ route('tasks.index') }}" class="text-sm text-indigo-600">Manage</a></div>
                <div class="flex flex-wrap gap-2">
                    @forelse($tasks->where('is_sticky', true) as $task)
                        <button class="inline-flex items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition hover:brightness-110 disabled:cursor-wait disabled:opacity-50" style="background-color:{{ $task->color_hex }};color:{{ $task->button_text_color }}" data-task-event="{{ route('events.store', [$log, $task]) }}" data-name="{{ $task->name }}" data-options='@json($task->options ?? [])'><span class="mr-2 h-3 w-3 rounded-sm border border-current opacity-80" style="background-color:{{ $task->color_hex }}"></span>{{ $task->name }} <span class="ml-2 rounded-full bg-white/20 px-2" data-count>{{ $counts[$task->id] ?? 0 }}</span></button>
                    @empty<p class="text-sm text-slate-500">Make a repeating task sticky to keep its button here.</p>@endforelse
                    @if($tasks->where('is_sticky', false)->isNotEmpty())
                        <details class="relative"><summary class="btn-secondary cursor-pointer">More events</summary><div class="absolute right-0 z-20 mt-2 grid w-60 gap-1 rounded-xl border bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">@foreach($tasks->where('is_sticky', false) as $task)<button class="flex items-center rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800" data-task-event="{{ route('events.store', [$log, $task]) }}" data-name="{{ $task->name }}" data-options='@json($task->options ?? [])'><span class="mr-2 h-4 w-4 shrink-0 rounded-sm border border-slate-300 dark:border-slate-600" style="background-color:{{ $task->color_hex }}"></span>{{ $task->name }} (<span data-count>{{ $counts[$task->id] ?? 0 }}</span>)</button>@endforeach</div></details>
                    @endif
                </div>
            </section>

            <section class="space-y-3" id="timeline">
                @forelse($log->blocks as $block)
                    @include('logs.partials.block', ['block' => $block])
                @empty<div class="panel py-12 text-center"><p class="text-lg font-semibold">A clear deck.</p><p class="mt-1 text-sm text-slate-500">Add a note, event, conversation, or recording to begin this day.</p></div>@endforelse
            </section>
        </div>

        <aside class="space-y-4">
            <section class="panel"><h2 class="mb-3 font-bold">Write a note</h2><form data-ajax method="POST" action="{{ route('blocks.store', $log) }}" class="space-y-3">@csrf<input type="hidden" name="type" value="text"><textarea class="input" name="content" rows="5" placeholder="What happened?" required></textarea><button class="btn w-full">Add to log</button></form></section>

            <section class="panel" data-recorder-panel>
                <h2 class="mb-3 font-bold">Photo, audio or video</h2>
                <form data-ajax method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', $log) }}" class="space-y-3"><input id="media-file" class="input text-sm" type="file" name="file" accept="image/*,audio/*,video/*" capture><button class="btn w-full">Upload attachment</button></form>
                <div class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn-secondary" data-record="audio" data-target="#media-file">Record audio</button><button type="button" class="btn-secondary" data-record="video" data-target="#media-file">Record video</button></div>
                <div data-recording-status role="status" aria-live="polite" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><div class="flex items-center gap-2"><span data-recording-dot class="h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><span data-recording-message class="font-medium">Ready to record.</span><time data-recording-time class="ml-auto font-mono text-xs">00:00</time></div></div>
                <video data-recording-preview class="mt-3 hidden max-h-56 w-full rounded-xl bg-black object-contain" muted playsinline></video>
                <p class="mt-2 text-xs text-slate-500">Your browser will ask for microphone or camera permission. Recording requires HTTPS or localhost.</p>
            </section>

            <section class="panel"><h2 class="mb-3 font-bold">Chat with the log</h2><form data-ajax method="POST" action="{{ route('openrouter.chat', $log) }}" class="space-y-3"><label class="label">Model</label><select class="input text-sm" name="model" data-model-select="chat" data-models-url="{{ route('openrouter.models') }}" required><option>Loading models…</option></select><textarea class="input" name="message" rows="4" placeholder="Ask, reflect, summarize…" required></textarea>@if($log->attachments->where('type','image')->isNotEmpty())<details><summary class="cursor-pointer text-sm text-indigo-600">Include images</summary><div class="mt-2 space-y-1">@foreach($log->attachments->where('type','image') as $image)<label class="flex items-center gap-2 text-xs"><input type="checkbox" name="attachment_ids[]" value="{{ $image->id }}">{{ $image->original_name }}</label>@endforeach</div></details>@endif<button class="btn w-full">Send & log reply</button></form></section>

            <section class="panel"><h2 class="mb-3 font-bold">Generate an image</h2><form data-ajax method="POST" action="{{ route('openrouter.images', $log) }}" class="space-y-3"><select class="input text-sm" name="model" data-model-select="image" data-models-url="{{ route('openrouter.models') }}" required><option>Loading image models…</option></select><textarea class="input" name="prompt" rows="3" placeholder="Describe the image…" required></textarea><button class="btn w-full">Generate & attach</button></form></section>

            <details class="panel"><summary class="cursor-pointer font-bold">API usage · ${{ number_format((float)$log->apiCalls->sum('cost'), 6) }}</summary><div class="mt-3 space-y-2">@forelse($log->apiCalls as $call)<div class="rounded-lg bg-slate-50 p-2 text-xs dark:bg-slate-950"><div class="flex justify-between"><strong>{{ ucfirst($call->operation) }}</strong><span>{{ $call->status_code }}</span></div><p class="truncate text-slate-500">{{ $call->model ?: 'Models endpoint' }}</p><p>{{ $call->total_tokens }} tokens · ${{ number_format((float)$call->cost, 8) }} · {{ $call->duration_ms }}ms</p>@if($call->error)<p class="text-rose-600">{{ $call->error }}</p>@endif</div>@empty<p class="text-sm text-slate-500">No calls for this day.</p>@endforelse</div></details>
        </aside>
    </div>
</x-app-layout>
