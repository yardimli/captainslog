<dialog class="w-[min(34rem,calc(100vw-2rem))] rounded-2xl bg-transparent p-0 backdrop:bg-slate-950/70" data-icon-crop-dialog>
    <div id="custom-icon-crop-panel" class="panel space-y-4">
        <div id="custom-icon-crop-heading" class="flex items-start gap-3"><div id="custom-icon-crop-heading-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Custom image</p><h2 class="text-xl font-black">Position your icon</h2><p class="mt-1 text-sm text-slate-500">Drag to pan. Zoom until the square contains exactly what you want saved.</p></div><button type="button" class="btn-secondary ml-auto" data-icon-crop-cancel>Cancel</button></div>
        <div id="custom-icon-crop-stage" class="relative mx-auto h-72 w-72 cursor-move touch-none overflow-hidden rounded-2xl bg-slate-950" data-icon-crop-stage>
            <img class="pointer-events-none absolute max-w-none select-none" alt="Image being cropped" draggable="false" data-icon-crop-image>
            <span class="pointer-events-none absolute left-0 top-0 h-20 w-72 bg-slate-950/70"></span>
            <span class="pointer-events-none absolute bottom-0 left-0 h-20 w-72 bg-slate-950/70"></span>
            <span class="pointer-events-none absolute left-0 top-20 h-32 w-20 bg-slate-950/70"></span>
            <span class="pointer-events-none absolute right-0 top-20 h-32 w-20 bg-slate-950/70"></span>
            <span class="pointer-events-none absolute left-20 top-20 h-32 w-32 border-2 border-white shadow-[0_0_0_1px_rgba(15,23,42,0.7)]"></span>
        </div>
        <div id="custom-icon-zoom-controls" class="flex items-center gap-3"><button type="button" class="btn-secondary h-10 w-10 p-0" data-icon-zoom-out aria-label="Zoom out">−</button><input type="range" min="1" max="4" value="1" step="0.01" class="min-w-0 flex-1" data-icon-zoom aria-label="Image zoom"><button type="button" class="btn-secondary h-10 w-10 p-0" data-icon-zoom-in aria-label="Zoom in">+</button></div>
        <button type="button" class="btn w-full" data-icon-crop-apply>Use cropped image</button>
    </div>
</dialog>
