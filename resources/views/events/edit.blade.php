<x-app-layout>
    <x-slot name="header"><div><p class="text-xs font-semibold uppercase text-emerald-600">Event already tracked at {{ $event->occurred_at->format('g:i A') }}</p><h1 class="text-xl font-bold">{{ $event->task_name }} @if($event->selected_value)· {{ $event->selected_value }}@endif</h1></div></x-slot>
    <div class="mx-auto grid max-w-4xl gap-5 p-4 sm:p-6 lg:grid-cols-2 lg:p-8">
        <section class="panel"><h2 class="mb-3 font-bold">Optional notes</h2><form method="POST" action="{{ route('events.update', $event) }}" class="space-y-3">@csrf @method('PATCH')<textarea class="input" name="notes" rows="12" placeholder="Anything else worth remembering?">{{ $event->block->content }}</textarea><button class="btn w-full">Save notes</button><a class="btn-secondary w-full" href="{{ route('logs.show', $event->dailyLog->log_date->toDateString()) }}">Skip / return to log</a></form></section>
        <section class="panel" data-recorder-panel>
            <h2 class="mb-3 font-bold">Attach media</h2>
            <form data-ajax method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', $event->dailyLog) }}" class="space-y-3">@csrf<input type="hidden" name="block_id" value="{{ $event->block_id }}"><input id="event-media" class="input" type="file" name="file" accept="image/*,audio/*,video/*" capture><button class="btn w-full">Upload</button></form>
            <div class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn-secondary" data-record="audio" data-target="#event-media">Record audio</button><button type="button" class="btn-secondary" data-record="video" data-target="#event-media">Record video</button></div>
            <div data-recording-status role="status" aria-live="polite" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><div class="flex items-center gap-2"><span data-recording-dot class="h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><span data-recording-message class="font-medium">Ready to record.</span><time data-recording-time class="ml-auto font-mono text-xs">00:00</time></div></div>
            <video data-recording-preview class="mt-3 hidden max-h-56 w-full rounded-xl bg-black object-contain" muted playsinline></video>
            <p class="mt-2 text-xs text-slate-500">Your browser will ask for microphone or camera permission. Recording requires HTTPS or localhost.</p>
            @foreach($event->block->attachments as $attachment)<p class="mt-3 truncate rounded-lg bg-slate-100 p-2 text-sm dark:bg-slate-950">{{ $attachment->original_name }}</p>@endforeach
        </section>
    </div>
</x-app-layout>
