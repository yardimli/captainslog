@php
    $pickerLabel = $label ?? 'Emoji';
    $pickerValue = $value ?? '📝';
@endphp

<div id="{{ $pickerId }}-field" class="relative {{ $containerClass ?? '' }}" data-emoji-picker data-emoji-source="{{ asset('data/data-by-group.json') }}">
    <label class="label" for="{{ $pickerId }}-input">{{ $pickerLabel }}</label>
    <input id="{{ $pickerId }}-input" type="hidden" name="{{ $name ?? 'emoji' }}" value="{{ $pickerValue }}" data-emoji-input>
    <button type="button" class="input flex items-center gap-3 text-left" data-emoji-toggle aria-expanded="false" aria-controls="{{ $pickerId }}-menu">
        <span class="text-2xl" data-emoji-preview aria-hidden="true">{{ $pickerValue }}</span>
        <span class="min-w-0 flex-1 text-sm font-semibold">Choose emoji</span>
        <span class="text-xs text-slate-400" aria-hidden="true">▼</span>
    </button>
    <div id="{{ $pickerId }}-menu" class="block-emoji-picker-menu absolute inset-x-0 top-full z-[100] mt-2 hidden max-h-[300px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" data-emoji-menu>
        <div id="{{ $pickerId }}-search-control" class="shrink-0 border-b border-slate-200 p-2 dark:border-slate-700">
            <label class="sr-only" for="{{ $pickerId }}-search">Search emojis</label>
            <input id="{{ $pickerId }}-search" type="search" class="input text-sm" placeholder="Search all emojis…" autocomplete="off" data-emoji-search>
        </div>
        <div id="{{ $pickerId }}-category-tabs" class="block-emoji-picker-category-tabs flex shrink-0 gap-1 overflow-x-auto border-b border-slate-200 p-1.5 dark:border-slate-700" role="tablist" aria-label="Emoji categories" data-emoji-categories></div>
        <div id="{{ $pickerId }}-emoji-grid" class="block-emoji-picker-grid grid min-h-0 flex-1 grid-cols-6 gap-1 overflow-y-auto p-2 sm:grid-cols-8" data-emoji-grid>
            <p class="col-span-full py-8 text-center text-sm text-slate-500" data-emoji-loading>Loading the complete emoji library…</p>
        </div>
        <p id="{{ $pickerId }}-empty-message" class="hidden px-3 pb-3 text-center text-sm text-slate-500" data-emoji-empty>No emojis match that search.</p>
        <template data-emoji-category-template><button type="button" class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" role="tab" aria-selected="false" data-emoji-category></button></template>
        <template data-emoji-option-template><button type="button" class="hidden aspect-square items-center justify-center rounded-xl text-2xl transition hover:bg-indigo-100 focus:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:hover:bg-indigo-950 dark:focus:bg-indigo-950" data-emoji-option></button></template>
    </div>
</div>
