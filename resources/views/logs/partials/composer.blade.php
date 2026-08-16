<div class="space-y-4">
    <section class="panel">
        <h2 class="mb-1 text-lg font-bold" data-note-heading>Write a note</h2>
        <p class="mb-3 text-sm text-slate-500">Choose the exact time above; the timeline will place it chronologically.</p>
        <form data-ajax data-composer-note-form method="POST" action="{{ route('blocks.store', $log) }}" data-create-action="{{ route('blocks.store', $log) }}" class="space-y-3">@csrf<input type="hidden" name="type" value="text"><input type="hidden" name="occurred_at" data-composer-time-field><textarea class="input" name="content" rows="6" placeholder="What happened?" required data-composer-content></textarea><p class="hidden text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-autosave-status role="status" aria-live="polite"></p><button class="btn w-full" data-composer-submit>Add to log</button></form>
    </section>

    <section class="panel" data-recorder-panel>
        <h2 class="mb-1 text-lg font-bold">Attach media</h2>
        <p class="mb-3 text-sm text-slate-500">Upload a file or record directly in the browser.</p>
        <form data-ajax data-composer-media-form method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', $log) }}" class="space-y-3"><input type="hidden" name="block_id" data-composer-block-field><input type="hidden" name="occurred_at" data-composer-time-field><input id="media-file" class="input text-sm" type="file" name="file" accept="image/*,audio/*,video/*" capture><button class="btn w-full">Upload attachment</button></form>
        <div class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn-secondary" data-record="audio" data-target="#media-file">Record audio</button><button type="button" class="btn-secondary" data-record="video" data-target="#media-file">Record video</button></div>
        <div data-recording-status role="status" aria-live="polite" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><div class="flex items-center gap-2"><span data-recording-dot class="h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><span data-recording-message class="font-medium">Ready to record.</span><time data-recording-time class="ml-auto font-mono text-xs">00:00</time></div></div>
        <video data-recording-preview class="mt-3 hidden max-h-56 w-full rounded-xl bg-black object-contain" muted playsinline></video>
        <p class="mt-2 text-xs text-slate-500">Your browser will ask for microphone or camera permission. Recording requires HTTPS or localhost.</p>
    </section>
</div>
