@php $recordedAt = $block->taskEvent?->occurred_at ?? $block->created_at; @endphp
<article class="panel group" id="block-{{ $block->id }}">
    <div class="mb-3 flex items-start gap-3"><span class="rounded-lg px-2 py-1 text-xs font-bold uppercase {{ $block->type === 'event' ? 'bg-emerald-100 text-emerald-800' : ($block->type === 'chat_assistant' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600') }}">{{ str_replace('_',' ', $block->type) }}</span><div class="ml-auto text-right text-xs text-slate-400"><time>Recorded {{ $recordedAt->format('g:i A') }}</time><p>Updated {{ $block->updated_at->format('g:i A') }}</p></div></div>
    @if($block->taskEvent)<div class="mb-2 text-lg font-bold">{{ $block->taskEvent->task_name }} @if($block->taskEvent->selected_value)<span class="text-indigo-600">· {{ $block->taskEvent->selected_value }}</span>@endif</div>@endif
    @if($block->content)<div class="whitespace-pre-wrap leading-relaxed">{{ $block->content }}</div>@endif
    @foreach($block->attachments as $attachment)
        <div class="mt-3 overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-950">
            @if($attachment->type === 'image')<img src="{{ $attachment->url }}" alt="{{ $attachment->original_name }}" class="max-h-[32rem] w-full object-contain" loading="lazy">
            @elseif($attachment->type === 'audio')<audio class="w-full" controls src="{{ $attachment->url }}"></audio><form data-ajax method="POST" action="{{ route('openrouter.transcribe', $attachment) }}" class="flex gap-2 p-2"><input class="input text-xs" name="model" value="openai/whisper-large-v3"><button class="btn-secondary">Transcribe</button></form>
            @elseif($attachment->type === 'video')<video class="max-h-[32rem] w-full" controls playsinline src="{{ $attachment->url }}"></video>@endif
            <div class="flex items-center justify-between p-2 text-xs text-slate-500"><span class="truncate">{{ $attachment->original_name }}</span><button data-delete="{{ route('attachments.destroy', $attachment) }}" class="text-rose-600">Delete media</button></div>
        </div>
    @endforeach
    <div class="mt-3 flex gap-3 border-t border-slate-100 pt-2 text-xs dark:border-slate-800">
        @if($block->type === 'event' && $block->taskEvent)<a class="text-indigo-600" href="{{ route('events.edit', $block->taskEvent) }}">Add/edit notes & media</a>@elseif(!in_array($block->type,['generated_image']))<button class="text-indigo-600" data-edit-block="{{ route('blocks.update', $block) }}" data-content="{{ e($block->content) }}">Edit</button>@endif
        <button class="text-rose-600" data-delete="{{ route('blocks.destroy', $block) }}">Delete</button>
    </div>
</article>
