<template id="toast-template">
    <div class="toast-message fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-xl dark:bg-white dark:text-slate-900" data-toast></div>
</template>

<template id="modal-template">
    <div class="modal-backdrop motion-backdrop-enter fixed inset-0 grid place-items-end bg-slate-950/60 p-3 sm:place-items-center" style="z-index:120" data-modal-backdrop>
        <div class="modal-panel motion-panel-enter w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900" data-modal-panel>
            <h2 class="text-lg font-bold" data-modal-title></h2>
            <p class="mt-2 hidden text-sm text-slate-500" data-modal-message></p>
            <select class="input mt-4 hidden" data-modal-select></select>
            <textarea class="input mt-4 hidden" rows="6" data-modal-textarea></textarea>
            <div class="modal-actions mt-5 grid grid-cols-2 gap-2">
                <button type="button" class="btn-secondary" data-modal-cancel>Cancel</button>
                <button type="button" class="btn" data-modal-confirm>Continue</button>
            </div>
        </div>
    </div>
</template>

<template id="select-option-template"><option></option></template>
<template id="ajax-method-template"><input type="hidden" name="_method"></template>

<template id="browsing-domain-row-template">
    <div class="block-browsing-domain-row flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <span class="min-w-0 truncate font-semibold" data-browsing-domain-name></span>
        <span class="shrink-0 rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-800 dark:bg-sky-950 dark:text-sky-200" data-browsing-domain-time></span>
    </div>
</template>

<template id="github-event-row-template">
    <div class="event-github-commit-row rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="event-github-commit-heading flex items-start justify-between gap-3"><time class="font-mono text-xs font-bold text-indigo-600" data-github-event-time></time><code class="rounded bg-slate-100 px-2 py-1 text-[11px] text-slate-500 dark:bg-slate-800" data-github-event-sha></code></div>
        <p class="mt-2 break-words text-sm font-semibold" data-github-event-message></p>
        <a class="mt-2 inline-flex text-xs font-bold text-indigo-600 hover:underline dark:text-indigo-400" target="_blank" rel="noopener noreferrer" data-github-event-link>Open commit</a>
    </div>
</template>

<template id="composer-image-preview-template">
    <figure class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <img class="aspect-square w-full object-cover" alt="Attached image preview" loading="lazy" data-composer-image-preview>
    </figure>
</template>

<template id="time-slot-row-template">
    <div class="time-slot-row flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-950">
        <div class="time-slot-picker min-w-0 flex-1" data-time-picker>
            <button type="button" class="btn-secondary w-full justify-center text-lg font-bold" data-time-picker-open></button>
            <input type="hidden" data-time-picker-input>
        </div>
        <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-xl font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950" aria-label="Remove time slot" data-time-slot-remove>&times;</button>
    </div>
</template>

<template id="time-picker-dialog-template">
    <div class="time-dialog-backdrop fixed inset-0" style="z-index:100" data-time-dialog-backdrop>
        <section class="fixed max-h-[80dvh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-300 bg-white p-4 shadow-2xl ring-1 ring-slate-950/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10" role="dialog" aria-modal="true" aria-label="Choose time" data-time-dialog-panel>
            <div class="time-dialog-heading mb-3 flex items-center gap-3">
                <div class="time-dialog-heading-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Choose time</p><h2 class="text-xl font-black" data-time-preview></h2></div>
                <button type="button" class="btn-secondary ml-auto" data-time-dialog-cancel>Cancel</button>
            </div>
            <div class="time-wheel-frame overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-inner dark:border-slate-700 dark:bg-slate-950">
                <div class="time-column-labels grid grid-cols-2 border-b border-slate-200 text-center text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700" data-time-column-grid>
                    <span class="border-r border-slate-200 px-2 py-2 dark:border-slate-700">Hour</span>
                    <span class="border-r border-slate-200 px-2 py-2 dark:border-slate-700">Minute</span>
                    <span class="hidden px-2 py-2" data-time-period-column>AM / PM</span>
                </div>
                <div class="time-wheel-grid relative grid grid-cols-2" data-time-wheel-grid>
                    <div class="time-wheel-selection pointer-events-none absolute inset-x-1 top-1/2 z-0 h-12 -translate-y-1/2 rounded-lg bg-indigo-600 shadow-sm"></div>
                    <div class="time-wheel relative z-10 h-72 snap-y snap-mandatory overflow-y-auto overscroll-contain border-r border-slate-200 touch-pan-y dark:border-slate-700" style="padding-block:120px" role="listbox" aria-label="Hour" data-time-wheel-hour-24>
                        @for($hour = 0; $hour < 24; $hour++)<button type="button" class="relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors" data-value="{{ $hour }}" role="option">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</button>@endfor
                    </div>
                    <div class="time-wheel relative z-10 hidden h-72 snap-y snap-mandatory overflow-y-auto overscroll-contain border-r border-slate-200 touch-pan-y dark:border-slate-700" style="padding-block:120px" role="listbox" aria-label="Hour" data-time-wheel-hour-12>
                        @for($hour = 1; $hour <= 12; $hour++)<button type="button" class="relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors" data-value="{{ $hour }}" role="option">{{ str_pad($hour, 2, '0', STR_PAD_LEFT) }}</button>@endfor
                    </div>
                    <div class="time-wheel relative z-10 h-72 snap-y snap-mandatory overflow-y-auto overscroll-contain border-r border-slate-200 touch-pan-y dark:border-slate-700" style="padding-block:120px" role="listbox" aria-label="Minute" data-time-wheel-minute>
                        @for($minute = 0; $minute < 60; $minute += 5)<button type="button" class="relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors" data-value="{{ $minute }}" role="option">{{ str_pad($minute, 2, '0', STR_PAD_LEFT) }}</button>@endfor
                    </div>
                    <div class="time-wheel relative z-10 hidden h-72 snap-y snap-mandatory overflow-y-auto overscroll-contain touch-pan-y" style="padding-block:120px" role="listbox" aria-label="AM or PM" data-time-wheel-period>
                        <button type="button" class="relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors" data-value="AM" role="option">AM</button>
                        <button type="button" class="relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors" data-value="PM" role="option">PM</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
