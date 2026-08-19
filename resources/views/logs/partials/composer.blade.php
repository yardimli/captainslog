<div id="log-composer-content" class="space-y-4">
    <section class="panel">
        <h2 class="mb-1 text-lg font-bold" data-note-heading>Write a note</h2>
        <p class="mb-3 text-sm text-slate-500">Choose the exact time above; the timeline will place it chronologically.</p>
        <form data-ajax data-composer-note-form method="POST" action="{{ route('blocks.store', $log) }}" data-create-action="{{ route('blocks.store', $log) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="type" value="text">
            <input type="hidden" name="occurred_at" data-composer-time-field>
            @include('partials.emoji-picker', ['pickerId' => 'composer-entry-emoji', 'name' => 'emoji', 'value' => '📝', 'label' => 'Entry emoji'])
            <textarea class="input" name="content" rows="6" placeholder="What happened?" required data-composer-content></textarea>
            <p class="hidden text-sm font-semibold text-emerald-600 dark:text-emerald-400" data-autosave-status role="status" aria-live="polite"></p>
            <button class="btn w-full" data-composer-submit>Add to log</button>
        </form>
    </section>

    <details class="panel group" data-recorder-panel data-composer-media-panel>
        <summary class="flex cursor-pointer list-none items-center gap-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <span class="min-w-0 flex-1"><span class="block text-lg font-bold">Attach media and text</span><span class="block text-sm text-slate-500">Upload media, record in the browser, or paste a long note.</span></span>
            <span class="shrink-0 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300" data-media-disclosure-label>Show</span>
        </summary>
        <div id="composer-media-controls" class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800">
            <div id="composer-existing-media" class="mb-3 hidden grid-cols-2 gap-2" data-composer-existing-media></div>
            <form data-ajax data-composer-media-form method="POST" enctype="multipart/form-data" action="{{ route('attachments.store', $log) }}" class="space-y-3"><input type="hidden" name="block_id" data-composer-block-field><input type="hidden" name="occurred_at" data-composer-time-field><input id="media-file" class="input text-sm" type="file" name="file" accept="image/*,audio/*,video/*" capture><button class="btn w-full">Upload attachment</button></form>
            <div id="composer-recording-actions" class="mt-2 grid grid-cols-2 gap-2"><button type="button" class="btn-secondary" data-record="audio" data-target="#media-file">Record audio</button><button type="button" class="btn-secondary" data-record="video" data-target="#media-file">Record video</button></div>
            <div id="composer-recording-status" data-recording-status role="status" aria-live="polite" class="mt-3 hidden rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-slate-700 dark:bg-slate-950"><div id="composer-recording-status-content" class="flex items-center gap-2"><span data-recording-dot class="h-2.5 w-2.5 shrink-0 rounded-full bg-indigo-500"></span><span data-recording-message class="font-medium">Ready to record.</span><time data-recording-time class="ml-auto font-mono text-xs">00:00</time></div></div>
            <video data-recording-preview class="mt-3 hidden max-h-56 w-full rounded-xl bg-black object-contain" muted playsinline></video>
            <p class="mt-2 text-xs text-slate-500">Your browser will ask for microphone or camera permission. Recording requires HTTPS or localhost.</p>
            <div id="composer-long-text-section" class="mt-5 border-t border-slate-200 pt-4 dark:border-slate-800">
                <h3 class="font-bold">Attach long text</h3>
                <p class="mt-1 text-xs text-slate-500">Paste a long plain-text or Markdown note. Pasted HTML is displayed as text and is never rendered.</p>
                <form data-ajax data-composer-long-text-form method="POST" action="{{ route('long-texts.store', $log) }}" class="mt-3 space-y-3">
                    @csrf
                    <input type="hidden" name="block_id" data-composer-block-field>
                    <input type="hidden" name="occurred_at" data-composer-time-field>
                    <label class="label" for="composer-long-text-format">Format</label>
                    <select id="composer-long-text-format" class="input" name="format"><option value="text">Plain text</option><option value="markdown">Markdown</option></select>
                    <label class="label" for="composer-long-text-content">Long text</label>
                    <textarea id="composer-long-text-content" class="input font-mono text-sm" name="content" rows="9" maxlength="10000000" placeholder="Paste the full note here…" required data-long-text-content></textarea>
                    <button type="submit" class="btn w-full" data-busy-label="Attaching long text"><span data-button-label>Attach long text</span></button>
                </form>
            </div>
        </div>
    </details>
</div>
