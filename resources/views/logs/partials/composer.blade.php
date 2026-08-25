<div id="log-composer-content" class="space-y-4">
    <section class="panel">
        <h2 class="mb-1 text-lg font-bold" data-note-heading>Write a note</h2>
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

</div>
