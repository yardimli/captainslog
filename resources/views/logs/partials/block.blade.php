<article class="panel group {{ $block->is_hidden ? 'ring-2 ring-amber-400' : '' }}" id="block-{{ $block->id }}">
    @php
        $typeLabelClass = $block->type === 'event'
            ? 'bg-emerald-100 text-emerald-800'
            : ($block->type === 'chat_assistant' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600');
    @endphp
    @if($block->taskEvent)
        <div class="flex flex-wrap items-center gap-2 leading-relaxed" data-block-description><span class="inline-flex rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>@if($block->is_hidden)<span class="inline-flex rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="text-lg font-bold">{{ $block->taskEvent->task_name }} @if($block->taskEvent->selected_value)<span class="text-indigo-600">· {{ $block->taskEvent->selected_value }}</span>@endif</span></div>
        @if($block->content)<div class="mt-2 whitespace-pre-wrap leading-relaxed">{{ $block->content }}</div>@endif
    @elseif($block->content)
        <div class="whitespace-pre-wrap leading-relaxed" data-block-description><span class="mr-2 inline-flex align-middle rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>@if($block->is_hidden)<span class="mr-2 inline-flex align-middle rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif{{ $block->content }}</div>
    @elseif($block->attachments->isNotEmpty())
        <div class="flex min-w-0 items-center gap-2 leading-relaxed" data-block-description><span class="inline-flex shrink-0 rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>@if($block->is_hidden)<span class="inline-flex shrink-0 rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800">Hidden</span>@endif<span class="truncate text-sm text-slate-600 dark:text-slate-300">{{ $block->attachments->pluck('original_name')->join(', ') }}</span></div>
    @else
        <span class="inline-flex rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $typeLabelClass }}" data-block-type-label>{{ str_replace('_',' ', $block->type) }}</span>
    @endif
    @foreach($block->attachments as $attachment)
        <div class="mt-3 overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-950">
            @if($attachment->type === 'image')<img src="{{ $attachment->url }}" alt="{{ $attachment->original_name }}" class="max-h-[32rem] w-full object-contain" loading="lazy">
            @elseif($attachment->type === 'audio')<audio class="w-full" controls src="{{ $attachment->url }}"></audio><form data-ajax method="POST" action="{{ route('openrouter.transcribe', $attachment) }}" class="flex gap-2 p-2"><input class="input text-xs" name="model" value="openai/whisper-large-v3"><button class="btn-secondary">Transcribe</button></form>
            @elseif($attachment->type === 'video')<video class="max-h-[32rem] w-full" controls playsinline src="{{ $attachment->url }}"></video>@endif
            <div class="flex items-center justify-between p-2 text-xs text-slate-500"><span class="truncate">{{ $attachment->original_name }}</span><button data-delete="{{ route('attachments.destroy', $attachment) }}" class="text-rose-600">Delete media</button></div>
        </div>
    @endforeach
</article>
