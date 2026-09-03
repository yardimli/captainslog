@php
    $pickerLabel = $label ?? 'Emoji';
    $pickerValue = $value ?? '📝';
@endphp

<div id="{{ $pickerId }}-field" class="relative {{ $containerClass ?? '' }}" data-emoji-picker data-emoji-url="{{ route('emojis.index', [], false) }}">
{{--    <label class="label" for="{{ $pickerId }}-input">{{ $pickerLabel }}</label>--}}
    <input id="{{ $pickerId }}-input" type="hidden" name="{{ $name ?? 'emoji' }}" value="{{ $pickerValue }}" data-emoji-input>
    <button type="button" class="input flex items-center gap-3 text-left" data-emoji-toggle aria-expanded="false" aria-controls="{{ $pickerId }}-menu">
        <span class="text-2xl" data-emoji-preview aria-hidden="true">{{ $pickerValue }}</span>
        <span class="min-w-0 flex-1 text-sm font-semibold">Choose emoji</span>
        <span class="text-xs text-slate-400" aria-hidden="true">▼</span>
    </button>
    <div id="{{ $pickerId }}-menu" class="block-emoji-picker-menu absolute inset-x-0 top-full z-[100] mt-2 hidden h-[300px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" data-emoji-menu>
        <div id="{{ $pickerId }}-search-control" class="flex shrink-0 items-center gap-2 border-b border-slate-200 p-2 dark:border-slate-700">
            <label class="sr-only" for="{{ $pickerId }}-search">Search emojis</label>
            <input id="{{ $pickerId }}-search" type="search" class="input min-w-0 flex-1 text-sm" placeholder="Search all emojis…" autocomplete="off" data-emoji-search>
            <label class="sr-only" for="{{ $pickerId }}-category">Emoji category</label>
            <select id="{{ $pickerId }}-category" class="input w-36 shrink-0 text-sm sm:w-44" aria-label="Emoji category" data-emoji-categories></select>
        </div>
        <div id="{{ $pickerId }}-grid-frame" class="block-emoji-picker-grid-frame relative min-h-0 flex-1 overflow-hidden">
            <div id="{{ $pickerId }}-emoji-grid" class="block-emoji-picker-grid grid h-full min-h-0 grid-cols-6 gap-1 overflow-x-hidden overflow-y-auto p-2 transition-opacity sm:grid-cols-8" data-emoji-grid></div>
            <div class="block-emoji-picker-loading absolute inset-0 hidden place-items-center bg-white/65 text-indigo-600 backdrop-blur-[1px] dark:bg-slate-900/70 dark:text-indigo-300" data-emoji-loading role="status" aria-live="polite">
                <span class="flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold shadow-lg dark:bg-slate-800"><svg class="h-5 w-5 animate-spin" data-emoji-loading-spinner viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 0-9-9v4a5 5 0 0 1 5 5h4Z"></path></svg><span data-emoji-loading-message>Loading emojis…</span></span>
            </div>
        </div>
        <p id="{{ $pickerId }}-empty-message" class="hidden px-3 pb-3 text-center text-sm text-slate-500" data-emoji-empty>No emojis match that search.</p>
        <template data-emoji-option-template><button type="button" class="hidden aspect-square items-center justify-center rounded-xl text-2xl transition hover:bg-indigo-100 focus:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-indigo-950 dark:focus:bg-indigo-950" data-emoji-option></button></template>
    </div>
</div>
