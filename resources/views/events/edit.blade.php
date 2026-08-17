@extends('layouts.app')

@section('header')
<div id="event-edit-page-heading"><p class="text-xs font-semibold uppercase text-emerald-600">Event already tracked at {{ auth()->user()->formatTime($event->occurred_at) }}</p><h1 class="text-xl font-bold">{{ $event->task_name }} @if($event->selected_value)· {{ $event->selected_value }}@endif</h1></div>
@endsection

@section('content')
    <div id="event-edit-page-container" class="mx-auto grid max-w-4xl gap-5 p-4 sm:p-6 lg:grid-cols-2 lg:p-8">
        <section class="panel"><h2 class="mb-3 font-bold">Optional notes</h2><form method="POST" action="{{ route('events.update', $event) }}" class="space-y-3" data-event-autosave-form>@csrf @method('PATCH')<div id="event-edit-time-field"><label class="label">Event time</label><div id="event-edit-time-picker" data-time-picker><button type="button" class="btn-secondary w-full justify-center text-lg font-bold" data-time-picker-open></button><input type="hidden" name="occurred_at" value="{{ $event->occurred_at->format('H:i') }}" data-time-picker-input data-shared-time-source="event-{{ $event->id }}"></div></div>@include('partials.emoji-picker', ['pickerId' => 'tracked-event-emoji-'.$event->id, 'name' => 'emoji', 'value' => $event->block->emoji, 'label' => 'Entry emoji'])<textarea class="input" name="notes" rows="12" placeholder="Anything else worth remembering?">{{ $event->block->content }}</textarea><p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-autosave-status role="status" aria-live="polite">Changes save automatically.</p><a class="btn-secondary w-full" href="{{ route('logs.show', $event->dailyLog->log_date->toDateString()) }}">Return to log</a></form></section>
        <section class="panel" data-recorder-panel>
            <h2 class="mb-3 font-bold">Attach media</h2>
            <form data-ajax method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', $event->dailyLog) }}" class="space-y-3">@csrf<input type="hidden" name="block_id" value="{{ $event->block_id }}"><input type="hidden" name="occurred_at" value="{{ $event->occurred_at->format('H:i') }}" data-shared-time-field="event-{{ $event->id }}"><input id="event-media" class="input" type="file" name="file" accept="image/*,audio/*,video/*" capture><button class="btn w-full">Upload</button></form>
            <div id="event-recording-actions" class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn-secondary" data-record="audio" data-target="#event-media">Record audio</button><button type="button" class="btn-secondary" data-record="video" data-target="#event-media">Record video</button></div>
            <div id="event-recording-status" data-recording-status role="status" aria-live="polite" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><div id="event-recording-status-content" class="flex items-center gap-2"><span data-recording-dot class="h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><span data-recording-message class="font-medium">Ready to record.</span><time data-recording-time class="ml-auto font-mono text-xs">00:00</time></div></div>
            <video data-recording-preview class="mt-3 hidden max-h-56 w-full rounded-xl bg-black object-contain" muted playsinline></video>
            <p class="mt-2 text-xs text-slate-500">Your browser will ask for microphone or camera permission. Recording requires HTTPS or localhost.</p>
            @foreach($event->block->attachments as $attachment)<p class="mt-3 truncate rounded-lg bg-slate-100 p-2 text-sm dark:bg-slate-950">{{ $attachment->original_name }}</p>@endforeach
        </section>
    </div>
@endsection
