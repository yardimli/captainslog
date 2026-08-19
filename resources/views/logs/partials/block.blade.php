<article class="panel group {{ $block->is_hidden ? 'ring-2 ring-amber-400' : '' }}" id="block-{{ $block->id }}">
    @php
        $typeLabelClass = $block->type === 'event'
            ? 'bg-emerald-100 text-emerald-800'
            : ($block->type === 'chat_assistant' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600');
    @endphp
    @if($block->type === 'sensor_github' && data_get($block->metadata, 'empty') !== true)
        @php $githubCommitCount = count(data_get($block->metadata, 'commits', [])); @endphp
        <div class="block-github-description flex flex-wrap items-center gap-2 leading-relaxed" data-block-description><span class="text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="inline-flex rounded-lg bg-slate-900 px-2 py-1 text-xs font-bold uppercase text-white dark:bg-slate-100 dark:text-slate-900" data-block-type-label>GitHub</span>@if($block->is_hidden)<span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="font-semibold">{{ $block->content }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $githubCommitCount }} {{ Str::plural('commit', $githubCommitCount) }}</span></div>
    @elseif($block->type === 'sensor_browser')
        <div class="block-browser-description flex flex-wrap items-center gap-2 leading-relaxed" data-block-description><span class="text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="inline-flex rounded-lg bg-sky-100 px-2 py-1 text-xs font-bold uppercase text-sky-800 dark:bg-sky-950 dark:text-sky-200" data-block-type-label>Browsing</span>@if($block->is_hidden)<span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="font-semibold">{{ $block->content }}</span></div>
    @elseif($block->type === 'sensor_kindle')
        <div class="block-kindle-description flex flex-wrap items-center gap-2 leading-relaxed" data-block-description><span class="text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-900 dark:bg-amber-950 dark:text-amber-200" data-block-type-label>Kindle</span>@if($block->is_hidden)<span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="font-semibold">{{ $block->content }}</span>@if(data_get($block->metadata, 'author'))<span class="text-sm text-slate-500">{{ data_get($block->metadata, 'author') }}</span>@endif</div>
    @elseif($block->taskEvent)
        <div class="block-event-description flex flex-wrap items-center gap-2 leading-relaxed" data-block-description><span class="text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="inline-flex rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>@if($block->is_hidden)<span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="text-lg font-bold">{{ $block->taskEvent->task_name }} @if($block->taskEvent->selected_value)<span class="text-indigo-600">· {{ $block->taskEvent->selected_value }}</span>@endif</span></div>
        @if($block->content)<div class="block-event-notes mt-2 whitespace-pre-wrap leading-relaxed">{{ $block->content }}</div>@endif
    @elseif($block->content)
        <div class="block-text-description whitespace-pre-wrap leading-relaxed" data-block-description><span class="mr-2 inline-block align-middle text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="mr-2 inline-flex align-middle rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>@if($block->is_hidden)<span class="mr-2 inline-flex align-middle rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif{{ $block->content }}</div>
    @elseif($block->attachments->isNotEmpty())
        @php
            $namedAttachments = $block->attachments->reject(fn ($attachment) => $attachment->type === 'image');
        @endphp
        <div class="block-media-description flex min-w-0 items-center gap-2 leading-relaxed" data-block-description>
            <span class="text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span>
            <span class="inline-flex shrink-0 rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>
            @if($block->is_hidden)
                <span class="inline-flex shrink-0 rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>
            @endif
            @if($namedAttachments->isNotEmpty())
                <span class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $namedAttachments->pluck('original_name')->join(', ') }}</span>
            @endif
        </div>
    @else
        <span class="mr-2 text-xl" data-block-emoji aria-hidden="true">{{ $block->emoji }}</span><span class="inline-flex rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>
    @endif
    @foreach($block->attachments as $attachment)
        <div class="block-attachment mt-3 overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-950">
            @if($attachment->type === 'image')
                <div class="block-image-frame group/image relative h-[512px] max-h-[512px]">
                    <img src="{{ $attachment->url }}" alt="Image attachment" class="h-[512px] max-h-[512px] w-full object-contain" loading="lazy">
                    <div class="block-image-controls absolute right-3 top-3 flex gap-2 opacity-100 transition sm:opacity-0 sm:group-hover/image:opacity-100 sm:focus-within:opacity-100">
                        <button type="button" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-950/80 text-white shadow-lg backdrop-blur hover:bg-indigo-600" data-image-preview-open data-image-url="{{ $attachment->url }}" data-image-name="{{ $attachment->original_name }}" aria-label="Open image" title="Open image"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 3h6v6M14 10l7-7M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/></svg></button>
                        <a href="{{ $attachment->url }}" download="{{ $attachment->original_name }}" class="grid h-10 w-10 place-items-center rounded-xl bg-slate-950/80 text-white shadow-lg backdrop-blur hover:bg-indigo-600" aria-label="Download image" title="Download image"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg></a>
                    </div>
                </div>
            @elseif($attachment->type === 'audio')
                <audio class="w-full" controls src="{{ $attachment->url }}"></audio>
                <form data-ajax method="POST" action="{{ route('openrouter.transcribe', $attachment) }}" class="flex gap-2 p-2"><input class="input text-xs" name="model" value="openai/whisper-large-v3"><button class="btn-secondary">Transcribe</button></form>
            @elseif($attachment->type === 'video')
                <video class="max-h-[32rem] w-full" controls playsinline src="{{ $attachment->url }}"></video>
            @endif
            @if($attachment->type !== 'image')
                <div class="block-attachment-footer flex items-center justify-between p-2 text-xs text-slate-500"><span class="truncate">{{ $attachment->original_name }}</span><button data-delete="{{ route('attachments.destroy', $attachment) }}" class="text-rose-600">Delete media</button></div>
            @endif
        </div>
    @endforeach
    @if($block->longText)
        <details class="block-long-text mt-3 overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-950">
            <summary class="flex cursor-pointer items-center gap-2 px-4 py-3 font-semibold"><span aria-hidden="true">📄</span><span class="flex-1">Long {{ $block->longText->format === 'markdown' ? 'Markdown' : 'text' }} note</span><span class="text-xs font-normal text-slate-500">{{ number_format(mb_strlen($block->longText->content)) }} characters</span></summary>
            @if($block->longText->format === 'markdown')
                <div class="block-long-text-markdown border-t border-slate-200 p-4 leading-relaxed dark:border-slate-700">{!! Str::markdown($block->longText->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
            @else
                <div class="block-long-text-plain whitespace-pre-wrap break-words border-t border-slate-200 p-4 font-mono text-sm leading-relaxed dark:border-slate-700">{{ $block->longText->content }}</div>
            @endif
        </details>
    @endif
</article>
