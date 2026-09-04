<div id="{{ $pickerId }}-custom-icon" class="space-y-2" data-icon-upload>
    <label class="label">Custom image <span class="font-normal text-slate-500">(instead of emoji)</span></label>
    <div id="{{ $pickerId }}-custom-icon-controls" class="flex flex-wrap items-center gap-3">
        <span class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-2xl border border-slate-300 bg-slate-100 dark:border-slate-700 dark:bg-slate-900" data-icon-preview-frame>
            <img class="hidden h-full w-full object-cover" alt="Custom icon preview" data-icon-preview>
            <span class="text-xs text-slate-400" data-icon-empty>128px</span>
        </span>
        <button type="button" class="btn-secondary" data-icon-choose>Choose image</button>
        <button type="button" class="hidden text-sm font-semibold text-rose-600" data-icon-remove>Use emoji instead</button>
    </div>
    <input type="file" class="hidden" accept="image/png,image/jpeg,image/webp,image/gif" data-icon-file>
    <input type="hidden" name="icon_data" data-icon-data>
    <input type="hidden" name="remove_icon" value="0" data-icon-remove-input>
    <p class="text-xs text-slate-500">Choose a photo, then position and zoom it inside a 128 × 128 square.</p>
</div>
