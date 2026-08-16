import './bootstrap';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const toast = (message, error = false) => {
    const el = document.createElement('div');
    el.className = `fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-xl px-4 py-3 text-sm font-semibold shadow-xl ${error ? 'bg-rose-600' : 'bg-slate-900 dark:bg-white dark:text-slate-900'} text-white`;
    el.textContent = message; document.body.append(el); setTimeout(() => el.remove(), 3500);
};

function modal({title, message = '', options = null, initial = null, confirmText = 'Continue', cancelText = 'Cancel'}) {
    return new Promise(resolve => {
        const backdrop = document.createElement('div');
        backdrop.className = 'motion-backdrop-enter fixed inset-0 z-50 grid place-items-end bg-slate-950/60 p-3 sm:place-items-center';
        const panel = document.createElement('div'); panel.className = 'motion-panel-enter w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900';
        const heading = document.createElement('h2'); heading.className = 'text-lg font-bold'; heading.textContent = title; panel.append(heading);
        if (message) { const copy = document.createElement('p'); copy.className = 'mt-2 text-sm text-slate-500'; copy.textContent = message; panel.append(copy); }
        let input = null;
        if (options) { input = document.createElement('select'); input.className = 'input mt-4'; options.forEach(value => input.add(new Option(value, value))); panel.append(input); }
        else if (initial !== null) { input = document.createElement('textarea'); input.className = 'input mt-4'; input.rows = 6; input.value = initial; panel.append(input); }
        const actions = document.createElement('div'); actions.className = 'mt-5 grid grid-cols-2 gap-2';
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-secondary'; cancel.textContent = cancelText;
        const confirm = document.createElement('button'); confirm.type = 'button'; confirm.className = 'btn'; confirm.textContent = confirmText;
        const close = value => { backdrop.remove(); resolve(value); };
        cancel.addEventListener('click', () => close(null)); confirm.addEventListener('click', () => close(input ? input.value : true));
        backdrop.addEventListener('click', event => { if (event.target === backdrop) close(null); });
        actions.append(cancel, confirm); panel.append(actions); backdrop.append(panel); document.body.append(backdrop); (input || confirm).focus();
    });
}

async function ajax(url, options = {}) {
    const response = await fetch(url, {...options, headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json', ...(options.headers || {})}});
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Something went wrong.');
    return body;
}

function setMobileNavigation(open) {
    const toggle = document.querySelector('[data-mobile-nav-toggle]');
    const menu = document.querySelector('[data-mobile-nav-menu]');
    if (!toggle || !menu) return;
    menu.classList.toggle('hidden', !open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function openOverlay(name) {
    const root = document.querySelector(`[data-overlay="${name}"]`); if (!root) return;
    document.querySelectorAll('[data-overlay][data-open="true"]').forEach(item => closeOverlay(item, true));
    root.classList.remove('hidden', 'pointer-events-none');
    if (name !== 'composer') root.classList.add('grid');
    root.dataset.open = 'true'; document.body.classList.add('overflow-hidden');
    requestAnimationFrame(() => {
        root.querySelector('[data-overlay-backdrop]')?.classList.replace('opacity-0', 'opacity-100');
        const panel = root.querySelector('[data-overlay-panel]');
        panel?.classList.remove('translate-x-full', 'translate-y-5', 'opacity-0');
        root.querySelector('[data-overlay-close]:not([data-overlay-backdrop])')?.focus();
    });
}

function closeOverlay(root, immediate = false) {
    if (!root) return;
    root.querySelector('[data-overlay-backdrop]')?.classList.replace('opacity-100', 'opacity-0');
    const panel = root.querySelector('[data-overlay-panel]');
    if (root.dataset.overlay === 'composer') panel?.classList.add('translate-x-full');
    else panel?.classList.add('translate-y-5', 'opacity-0');
    root.dataset.open = 'false'; root.classList.add('pointer-events-none'); document.body.classList.remove('overflow-hidden');
    const finish = () => { if (immediate || root.dataset.open !== 'true') { root.classList.add('hidden'); root.classList.remove('grid'); } };
    if (immediate) finish(); else setTimeout(finish, 300);
}

const accountTimeFormat = document.body.dataset.timeFormat || '24';

function formatClock(value) {
    const [hour, minute] = (value || '00:00').split(':').map(Number);
    if (accountTimeFormat === '24') return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    return `${hour % 12 || 12}:${String(minute).padStart(2, '0')} ${hour < 12 ? 'AM' : 'PM'}`;
}

function parseClock(value) {
    const clean = value.trim().toUpperCase();
    let match = clean.match(/^(\d{1,2}):([0-5]\d)\s*(AM|PM)$/);
    if (match) {
        let hour = Number(match[1]); if (hour < 1 || hour > 12) return null;
        if (match[3] === 'AM' && hour === 12) hour = 0;
        if (match[3] === 'PM' && hour !== 12) hour += 12;
        return `${String(hour).padStart(2, '0')}:${match[2]}`;
    }
    match = clean.match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
    return match ? `${String(Number(match[1])).padStart(2, '0')}:${match[2]}` : null;
}

function openTimePicker(root) {
    const input = root.querySelector('[data-time-picker-input]');
    const anchor = root.querySelector('[data-time-picker-open]');
    const [initialHour, initialMinute] = (input.value || '12:00').split(':').map(Number);
    let hour = initialHour, minute = Math.round(initialMinute / 5) * 5;
    if (minute === 60) { minute = 0; hour = (hour + 1) % 24; }
    let period = hour >= 12 ? 'PM' : 'AM';
    let displayHour = hour % 12 || 12;
    const backdrop = document.createElement('div'); backdrop.className = 'fixed inset-0'; backdrop.style.zIndex = '100';
    const panel = document.createElement('section'); panel.className = 'fixed max-h-[calc(100dvh-1.5rem)] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-300 bg-white p-4 shadow-2xl ring-1 ring-slate-950/5 dark:border-slate-700 dark:bg-slate-900 dark:ring-white/10'; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-label', 'Choose time');
    const heading = document.createElement('div'); heading.className = 'mb-3 flex items-center gap-3'; heading.innerHTML = '<div><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Choose time</p><h2 class="text-xl font-black" data-time-preview></h2></div>';
    const close = document.createElement('button'); close.type = 'button'; close.className = 'btn-secondary ml-auto'; close.textContent = 'Cancel'; heading.append(close);
    const wheelFrame = document.createElement('div'); wheelFrame.className = 'overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-inner dark:border-slate-700 dark:bg-slate-950';
    const labels = document.createElement('div'); labels.className = `grid ${accountTimeFormat === '12' ? 'grid-cols-3' : 'grid-cols-2'} border-b border-slate-200 text-center text-xs font-bold uppercase tracking-wider text-slate-500 dark:border-slate-700`;
    ['Hour', 'Minute', ...(accountTimeFormat === '12' ? ['AM / PM'] : [])].forEach(label => { const item = document.createElement('span'); item.className = 'border-r border-slate-200 px-2 py-2 last:border-r-0 dark:border-slate-700'; item.textContent = label; labels.append(item); });
    const wheels = document.createElement('div'); wheels.className = `relative grid ${accountTimeFormat === '12' ? 'grid-cols-3' : 'grid-cols-2'}`;
    const selection = document.createElement('div'); selection.className = 'pointer-events-none absolute inset-x-1 top-1/2 z-0 h-12 -translate-y-1/2 rounded-lg bg-indigo-600 shadow-sm'; wheels.append(selection);
    const wheelControls = [];
    const makeWheel = (name, values, selected, formatter, choose) => {
        const list = document.createElement('div'); list.className = 'time-wheel relative z-10 h-72 snap-y snap-mandatory overflow-y-auto overscroll-contain border-r border-slate-200 touch-pan-y last:border-r-0 dark:border-slate-700'; list.setAttribute('role', 'listbox'); list.setAttribute('aria-label', name); list.style.paddingBlock = '120px';
        const buttons = values.map(value => {
            const item = document.createElement('button'); item.type = 'button'; item.className = 'relative z-10 flex h-12 w-full snap-center items-center justify-center text-base font-semibold transition-colors'; item.dataset.value = value; item.textContent = formatter(value); item.setAttribute('role', 'option');
            item.addEventListener('click', () => { choose(value); list.scrollTo({top: values.indexOf(value) * 48, behavior:'smooth'}); render(); }); list.append(item); return item;
        });
        let scrollTimer;
        list.addEventListener('scroll', () => { clearTimeout(scrollTimer); scrollTimer = setTimeout(() => { const index = Math.max(0, Math.min(values.length - 1, Math.round(list.scrollTop / 48))); choose(values[index]); list.scrollTo({top:index * 48, behavior:'smooth'}); render(); }, 80); });
        const control = {list, values, buttons, selected, update() { const current = selected(); buttons.forEach(button => { const active = String(button.dataset.value) === String(current); button.classList.toggle('text-white', active); button.classList.toggle('text-slate-800', !active); button.classList.toggle('dark:text-slate-200', !active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); }, center() { list.scrollTop = values.indexOf(selected()) * 48; }};
        wheelControls.push(control); wheels.append(list); return control;
    };
    const hourValues = accountTimeFormat === '12' ? Array.from({length:12}, (_, index) => index + 1) : Array.from({length:24}, (_, index) => index);
    makeWheel('Hour', hourValues, () => accountTimeFormat === '12' ? displayHour : hour, value => String(value).padStart(2, '0'), value => { if (accountTimeFormat === '12') { displayHour = value; hour = (value % 12) + (period === 'PM' ? 12 : 0); } else hour = value; });
    const minuteValues = Array.from({length:12}, (_, index) => index * 5);
    makeWheel('Minute', minuteValues, () => minute, value => String(value).padStart(2, '0'), value => { minute = value; });
    if (accountTimeFormat === '12') makeWheel('AM or PM', ['AM', 'PM'], () => period, value => value, value => { period = value; hour = (displayHour % 12) + (period === 'PM' ? 12 : 0); });
    wheelFrame.append(labels, wheels);
    const keyboard = document.createElement('div'); keyboard.className = 'mt-4';
    const keyboardToggle = document.createElement('button'); keyboardToggle.type = 'button'; keyboardToggle.className = 'btn-secondary w-full'; keyboardToggle.textContent = 'Manual input';
    const keyboardField = document.createElement('div'); keyboardField.className = 'mt-3 hidden'; keyboardField.innerHTML = `<label class="label">Free time entry</label><input class="input" data-free-time placeholder="${accountTimeFormat === '12' ? '6:37 PM' : '18:37'}"><p class="mt-1 hidden text-xs text-rose-600" data-time-error>Enter a valid time.</p>`;
    keyboardToggle.addEventListener('click', () => { keyboardField.classList.toggle('hidden'); if (!keyboardField.classList.contains('hidden')) { const field = keyboardField.querySelector('[data-free-time]'); field.value = formatClock(`${String(hour).padStart(2,'0')}:${String(minute).padStart(2,'0')}`); field.focus(); } }); keyboard.append(keyboardToggle, keyboardField);
    const actions = document.createElement('div'); actions.className = 'mt-3 grid grid-cols-2 gap-2';
    const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-secondary'; cancel.textContent = 'Cancel';
    const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'btn'; apply.textContent = 'Use this time'; actions.append(cancel, apply);
    const positionPanel = () => {
        const margin = 12, gap = 8, anchorRect = anchor.getBoundingClientRect();
        const container = root.closest('[data-overlay-panel], .panel'), containerRect = container?.getBoundingClientRect();
        const width = Math.min(448, containerRect?.width || 448, window.innerWidth);
        panel.style.width = `${width}px`;
        const idealLeft = anchorRect.left + (anchorRect.width / 2) - (width / 2);
        panel.style.left = `${Math.max(0, Math.min(idealLeft, window.innerWidth - width))}px`;
        const naturalHeight = Math.min(panel.scrollHeight, window.innerHeight - (margin * 2));
        const roomBelow = window.innerHeight - anchorRect.bottom - gap;
        const opensBelow = roomBelow >= naturalHeight;
        const availableHeight = opensBelow ? naturalHeight : Math.min(naturalHeight, Math.max(160, anchorRect.top - gap - margin));
        panel.style.maxHeight = `${availableHeight}px`;
        const height = Math.min(panel.scrollHeight, availableHeight);
        const idealTop = opensBelow ? anchorRect.bottom + gap : anchorRect.top - height - gap;
        panel.style.top = `${Math.max(margin, Math.min(idealTop, window.innerHeight - height - margin))}px`;
        panel.dataset.placement = opensBelow ? 'below' : 'above';
    };
    const dismiss = () => { window.removeEventListener('resize', positionPanel); window.removeEventListener('scroll', positionPanel, true); backdrop.remove(); };
    close.addEventListener('click', dismiss); backdrop.addEventListener('click', event => { if (event.target === backdrop) dismiss(); });
    const render = () => {
        const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`; heading.querySelector('[data-time-preview]').textContent = formatClock(value); wheelControls.forEach(control => control.update());
    };
    apply.addEventListener('click', () => {
        if (!keyboardField.classList.contains('hidden') && keyboardField.querySelector('[data-free-time]').value.trim()) {
            const parsed = parseClock(keyboardField.querySelector('[data-free-time]').value); if (!parsed) { keyboardField.querySelector('[data-time-error]').classList.remove('hidden'); return; }
            [hour, minute] = parsed.split(':').map(Number);
        }
        input.value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`; input.dispatchEvent(new Event('input', {bubbles:true})); input.dispatchEvent(new Event('change', {bubbles:true})); root.querySelector('[data-time-picker-open]').textContent = formatClock(input.value); dismiss();
    });
    cancel.addEventListener('click', dismiss);
    panel.append(heading, wheelFrame, keyboard, actions); backdrop.append(panel); document.body.append(backdrop); render(); positionPanel(); window.addEventListener('resize', positionPanel); window.addEventListener('scroll', positionPanel, true); requestAnimationFrame(() => { wheelControls.forEach(control => control.center()); positionPanel(); });
}

function initTimePicker(root) {
    const input = root.querySelector('[data-time-picker-input]'), button = root.querySelector('[data-time-picker-open]'); if (!input || !button) return;
    const update = () => { button.textContent = formatClock(input.value || '12:00'); }; button.addEventListener('click', () => openTimePicker(root)); input.addEventListener('change', update); update();
}

document.querySelectorAll('[data-time-picker]').forEach(initTimePicker);

document.addEventListener('change', event => {
    const source = event.target.closest('[data-shared-time-source]'); if (!source) return;
    document.querySelectorAll(`[data-shared-time-field="${source.dataset.sharedTimeSource}"]`).forEach(field => { field.value = source.value; });
});

document.querySelectorAll('[data-time-slots]').forEach(editor => {
    const list = editor.querySelector('[data-time-slot-list]'), name = editor.dataset.name || 'scheduled_times[]';
    const addSlot = (value, open = false) => {
        const row = document.createElement('div'); row.className = 'flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-700 dark:bg-slate-950';
        const picker = document.createElement('div'); picker.className = 'min-w-0 flex-1'; picker.dataset.timePicker = '';
        const choose = document.createElement('button'); choose.type = 'button'; choose.className = 'btn-secondary w-full justify-center text-lg font-bold'; choose.dataset.timePickerOpen = '';
        const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; input.dataset.timePickerInput = '';
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'grid h-11 w-11 shrink-0 place-items-center rounded-xl text-xl font-bold text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950'; remove.setAttribute('aria-label', 'Remove time slot'); remove.textContent = '×'; remove.addEventListener('click', () => row.remove());
        picker.append(choose, input); row.append(picker, remove); list.append(row); initTimePicker(picker); if (open) choose.click();
    };
    let values = []; try { values = JSON.parse(editor.dataset.values || '[]'); } catch (_) {}
    values.forEach(value => addSlot(value));
    editor.querySelector('[data-time-slot-add]')?.addEventListener('click', () => { const date = new Date(), five = Math.ceil(date.getMinutes() / 5) * 5, hour = (date.getHours() + Math.floor(five / 60)) % 24; addSlot(`${String(hour).padStart(2,'0')}:${String(five % 60).padStart(2,'0')}`, true); });
});

function syncComposerTime() {
    const time = document.querySelector('[data-composer-time]')?.value || '';
    document.querySelectorAll('[data-composer-time-field]').forEach(field => { field.value = time; });
}

const eventAutosaveTimers = new WeakMap();
const eventAutosaveRevisions = new WeakMap();

function isEventAutosaveForm(form) {
    return form?.matches('[data-event-autosave-form]') || form?.dataset.eventAutosave === 'true';
}

function scheduleEventAutosave(form, delay = 650) {
    if (!isEventAutosaveForm(form)) return;
    clearTimeout(eventAutosaveTimers.get(form));
    eventAutosaveTimers.set(form, setTimeout(async () => {
        const status = form.querySelector('[data-autosave-status]');
        const notes = form.querySelector('textarea[name="notes"]')?.value ?? '';
        const occurredAt = form.querySelector('[name="occurred_at"]')?.value;
        const revision = (eventAutosaveRevisions.get(form) || 0) + 1;
        eventAutosaveRevisions.set(form, revision);
        if (status) { status.classList.remove('hidden', 'text-emerald-600', 'dark:text-emerald-400', 'text-rose-600', 'dark:text-rose-400'); status.classList.add('text-indigo-600', 'dark:text-indigo-400'); status.textContent = 'Saving…'; }
        try {
            const body = await ajax(form.action, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({notes, occurred_at:occurredAt})});
            if (eventAutosaveRevisions.get(form) !== revision) return;
            if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400'); status.classList.add('text-emerald-600', 'dark:text-emerald-400'); status.textContent = 'Saved automatically.'; }
            const updatedLabel = form.closest('[data-overlay="composer"]')?.querySelector('[data-composer-updated]');
            if (updatedLabel && body.updated_time) { updatedLabel.textContent = `Updated ${body.updated_time}`; updatedLabel.classList.remove('hidden'); }
        } catch (error) {
            if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'text-emerald-600', 'dark:text-emerald-400'); status.classList.add('text-rose-600', 'dark:text-rose-400'); status.textContent = 'Could not save. Keep this editor open and try again.'; }
            toast(error.message, true);
        }
    }, delay));
}

function configureComposer({time, mode = 'create', kind = 'block', action = '', content = '', updated = '', hideUrl = '', deleteUrl = '', isHidden = false, blockId = ''} = {}) {
    const root = document.querySelector('[data-overlay="composer"]');
    const timeInput = root?.querySelector('[data-composer-time]');
    const form = root?.querySelector('[data-composer-note-form]');
    const textarea = root?.querySelector('[data-composer-content]');
    const updatedLabel = root?.querySelector('[data-composer-updated]');
    if (!root || !timeInput || !form || !textarea) return;
    clearTimeout(eventAutosaveTimers.get(form));
    form.dataset.eventAutosave = 'false';
    timeInput.value = time || new Date().toTimeString().slice(0, 5);
    timeInput.dispatchEvent(new Event('change', {bubbles:true}));
    form.action = mode === 'edit' ? action : form.dataset.createAction;
    let method = form.querySelector('input[name="_method"]');
    if (mode === 'edit') {
        if (!method) { method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; form.append(method); }
        method.value = 'PATCH';
    } else method?.remove();
    textarea.name = kind === 'event' ? 'notes' : 'content';
    textarea.value = content || '';
    textarea.required = mode === 'create';
    root.querySelector('[data-composer-title]').textContent = mode === 'edit' ? 'Edit log entry' : 'Add to this log';
    if (updatedLabel) { updatedLabel.textContent = updated ? `Updated ${updated}` : ''; updatedLabel.classList.toggle('hidden', mode !== 'edit' || !updated); }
    root.querySelector('[data-note-heading]').textContent = kind === 'event' ? 'Event notes' : (mode === 'edit' ? 'Edit note' : 'Write a note');
    const eventAutosave = mode === 'edit' && kind === 'event';
    const submit = root.querySelector('[data-composer-submit]');
    submit.textContent = mode === 'edit' ? 'Save changes' : 'Add to log';
    submit.classList.toggle('hidden', eventAutosave);
    const autosaveStatus = form.querySelector('[data-autosave-status]');
    if (autosaveStatus) { autosaveStatus.classList.toggle('hidden', !eventAutosave); autosaveStatus.textContent = eventAutosave ? 'Changes save automatically.' : ''; }
    const entryActions = root.querySelector('[data-composer-entry-actions]');
    const visibility = root.querySelector('[data-composer-visibility]');
    const deleteButton = root.querySelector('[data-composer-delete]');
    const showActions = mode === 'edit' && Boolean(hideUrl) && Boolean(deleteUrl);
    entryActions?.classList.toggle('hidden', !showActions); entryActions?.classList.toggle('grid', showActions);
    if (visibility) { visibility.textContent = isHidden ? 'Restore' : 'Hide'; visibility.dataset.plannerVisibility = hideUrl; visibility.dataset.method = 'PATCH'; visibility.dataset.payload = JSON.stringify({hidden:!isHidden}); }
    if (deleteButton) deleteButton.dataset.delete = deleteUrl;
    root.querySelector('[data-composer-block-field]').value = mode === 'edit' ? blockId : '';
    syncComposerTime();
    form.dataset.eventAutosave = eventAutosave ? 'true' : 'false';
    openOverlay('composer');
    setTimeout(() => textarea.focus(), 320);
}

function gapTime(item, event) {
    const minutes = value => { const [hour, minute] = value.split(':').map(Number); return hour * 60 + minute; };
    const from = minutes(item.dataset.from), to = item.dataset.to === '24:00' ? 1440 : minutes(item.dataset.to);
    const rect = item.getBoundingClientRect();
    const ratio = rect.height ? Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)) : 0.5;
    const selected = Math.min(1439, Math.round((from + ((to - from) * ratio)) / 5) * 5);
    return `${String(Math.floor(selected / 60)).padStart(2, '0')}:${String(selected % 60).padStart(2, '0')}`;
}

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-ajax]'); if (!form) return;
    if (isEventAutosaveForm(form)) { e.preventDefault(); scheduleEventAutosave(form, 0); return; }
    e.preventDefault(); const button = form.querySelector('[type=submit]'); if (button) button.disabled = true;
    try { const body = await ajax(form.action, {method: form.method || 'POST', body: new FormData(form)}); toast(body.message || 'Saved.'); if (body.reload || form.matches('[data-composer-note-form]')) location.reload(); else form.reset(); }
    catch (error) { toast(error.message, true); } finally { if (button) button.disabled = false; }
});

document.addEventListener('submit', event => {
    const form = event.target.closest('[data-event-autosave-form]'); if (!form) return;
    event.preventDefault(); scheduleEventAutosave(form, 0);
});

document.addEventListener('input', event => {
    const form = event.target.closest('[data-event-autosave-form], [data-composer-note-form][data-event-autosave="true"]');
    if (form && event.target.matches('textarea[name="notes"]')) scheduleEventAutosave(form);
});

document.addEventListener('change', event => {
    const form = event.target.closest('[data-event-autosave-form]');
    if (form && event.target.matches('[name="occurred_at"]')) scheduleEventAutosave(form, 0);
});

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-confirm-event-delete]');
    if (!form || form.dataset.confirmed === 'true') return;
    e.preventDefault();
    const confirmed = await modal({
        title:'Delete this event?',
        message:'The event button and setup will be deleted. Its recorded entries, notes, timestamps, and media will remain as editable text entries.',
        confirmText:'Delete event',
    });
    if (confirmed) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
});

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-smart-chat-form]'); if (!form) return;
    e.preventDefault();
    const button = form.querySelector('[type=submit]'), result = form.parentElement.querySelector('[data-chat-result]');
    button.disabled = true; button.textContent = 'Thinking...';
    result.classList.remove('hidden'); result.replaceChildren();
    const status = document.createElement('p'); status.className = 'font-semibold text-indigo-600 dark:text-indigo-400'; status.textContent = 'Classifying your message, then preparing the response...'; result.append(status);
    try {
        const body = await ajax(form.action, {method:'POST', body:new FormData(form)});
        result.replaceChildren();
        if (body.kind === 'answer') {
            const heading = document.createElement('p'); heading.className = 'mb-2 font-bold'; heading.textContent = 'Answer';
            const answer = document.createElement('div'); answer.className = 'whitespace-pre-wrap leading-relaxed'; answer.textContent = body.answer;
            result.append(heading, answer); form.querySelector('textarea[name=message]').value = '';
        } else if (body.kind === 'action') {
            const heading = document.createElement('p'); heading.className = 'font-bold text-amber-700 dark:text-amber-300'; heading.textContent = 'Confirm these actions';
            const summary = document.createElement('pre'); summary.className = 'mt-2 whitespace-pre-wrap font-sans leading-relaxed'; summary.textContent = body.summary;
            const actions = document.createElement('div'); actions.className = 'mt-4 grid grid-cols-2 gap-2';
            const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn-secondary'; cancel.textContent = 'Not now';
            const confirm = document.createElement('button'); confirm.type = 'button'; confirm.className = 'btn'; confirm.textContent = 'Confirm & run';
            cancel.addEventListener('click', () => result.classList.add('hidden'));
            confirm.addEventListener('click', async () => {
                confirm.disabled = true; confirm.textContent = 'Applying...';
                try { const confirmed = await ajax(body.confirm_url, {method:'POST'}); toast(confirmed.message || 'Actions completed.'); if (confirmed.reload) location.reload(); }
                catch (error) { toast(error.message, true); confirm.disabled = false; confirm.textContent = 'Confirm & run'; }
            });
            actions.append(cancel, confirm); result.append(heading, summary, actions);
        }
        toast(body.message || 'Chat response ready.');
    } catch (error) {
        result.replaceChildren(); const message = document.createElement('p'); message.className = 'text-rose-600'; message.textContent = error.message; result.append(message); toast(error.message, true);
    } finally { button.disabled = false; button.textContent = 'Send'; }
});

document.addEventListener('click', async e => {
    document.querySelectorAll('[data-events-menu][open]').forEach(menu => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
    const mobileToggle = e.target.closest('[data-mobile-nav-toggle]');
    if (mobileToggle) setMobileNavigation(mobileToggle.getAttribute('aria-expanded') !== 'true');
    else if (!e.target.closest('[data-mobile-nav-menu]')) setMobileNavigation(false);
    const overlayTrigger = e.target.closest('[data-panel-open]');
    const composerTrigger = e.target.closest('[data-composer-open]');
    const overlayClose = e.target.closest('[data-overlay-close]');
    if (overlayTrigger) { setMobileNavigation(false); openOverlay(overlayTrigger.dataset.panelOpen); }
    if (composerTrigger) configureComposer({time: composerTrigger.dataset.currentTime || composerTrigger.dataset.defaultTime});
    if (overlayClose) closeOverlay(overlayClose.closest('[data-overlay]'));
    if (e.target.closest('[data-theme-toggle]')) { document.documentElement.classList.toggle('dark'); localStorage.setItem('captainslog.theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light'); }
    const timelineItem = e.target.closest('.timeline-item');
    const nestedAction = e.target.closest('button, a, input, textarea, select, form, audio, video');
    if (timelineItem && !composerTrigger && !e.target.closest('[data-task-event]') && (!nestedAction || nestedAction === timelineItem)) {
        if (timelineItem.matches('[data-timeline-edit]')) configureComposer({time: timelineItem.dataset.timelineTime, mode:'edit', kind:timelineItem.dataset.editKind, action:timelineItem.dataset.editUrl, content:timelineItem.dataset.editContent, updated:timelineItem.dataset.editUpdated, hideUrl:timelineItem.dataset.hideUrl, deleteUrl:timelineItem.dataset.deleteUrl, isHidden:timelineItem.dataset.isHidden === 'true', blockId:timelineItem.dataset.blockId});
        else if (timelineItem.matches('[data-time-gap]')) configureComposer({time:gapTime(timelineItem, e)});
        else configureComposer({time:timelineItem.dataset.timelineTime || timelineItem.dataset.currentTime});
    }
    const visibility = e.target.closest('[data-planner-visibility]');
    if (visibility) {
        visibility.disabled = true;
        try {
            const payload = JSON.parse(visibility.dataset.payload || '{}');
            const body = await ajax(visibility.dataset.plannerVisibility, {method:visibility.dataset.method || 'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
            toast(body.message); location.reload();
        } catch (error) { toast(error.message, true); visibility.disabled = false; }
    }
    const del = e.target.closest('[data-delete]');
    if (del && await modal({title:'Delete this item?', message:'This cannot be undone.', confirmText:'Delete'})) { try { const body = await ajax(del.dataset.delete, {method:'DELETE'}); toast(body.message); location.reload(); } catch(error) { toast(error.message, true); } }
    const edit = e.target.closest('[data-edit-block]');
    if (edit) { const content = await modal({title:'Edit log entry', message:edit.dataset.updated ? `Updated ${edit.dataset.updated}` : '', initial:edit.dataset.content || '', confirmText:'Save'}); if (content !== null) try { await ajax(edit.dataset.editBlock,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({content})}); location.reload(); } catch(error) { toast(error.message,true); } }
    const task = e.target.closest('[data-task-event]');
    if (task) {
        task.closest('[data-events-menu]')?.removeAttribute('open');
        let value = null; const options = JSON.parse(task.dataset.options || '[]');
        if (options.length) { value = await modal({title:task.dataset.name, message:'Choose a value before this event is tracked.', options, confirmText:'Track event'}); if (value === null) return; }
        task.disabled = true;
        try {
            const body = await ajax(task.dataset.taskEvent,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({value})});
            document.querySelectorAll(`[data-task-event="${task.dataset.taskEvent}"] [data-count]`).forEach(count => { count.textContent = body.count; });
            toast(body.message);
            const addNotes = body.edit_url && await modal({title:'Event tracked', message:'Would you like to attach notes, a photo, or a recording?', confirmText:'Add notes & media', cancelText:'Done'});
            if (addNotes) configureComposer({time:body.time, mode:'edit', kind:'event', action:body.edit_url, content:'', hideUrl:body.hide_url, deleteUrl:body.delete_url, blockId:body.block_id});
            else location.reload();
        }
        catch(error) { toast(error.message,true); } finally { task.disabled = false; }
    }
});

const composerTimeInput = document.querySelector('[data-composer-time]');
composerTimeInput?.addEventListener('input', syncComposerTime);
composerTimeInput?.addEventListener('change', () => {
    syncComposerTime();
    const form = document.querySelector('[data-composer-note-form][data-event-autosave="true"]');
    if (form) scheduleEventAutosave(form, 0);
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-events-menu][open]').forEach(menu => menu.removeAttribute('open'));
        document.querySelectorAll('[data-overlay][data-open="true"]').forEach(root => closeOverlay(root));
        setMobileNavigation(false);
    }
});

const viewSelect = document.querySelector('[data-calendar-view]');
if (viewSelect) {
    const params = new URLSearchParams(location.search), saved = localStorage.getItem('captainslog.calendarView');
    const navigate = view => { localStorage.setItem('captainslog.calendarView', view); if (view === 'day') { location.href = viewSelect.dataset.dayUrl; return; } params.set('view', view); location.href = `${location.pathname}?${params}`; };
    const requested = params.get('view') || saved;
    if (requested === 'day') location.replace(viewSelect.dataset.dayUrl);
    else if (!params.has('view') && saved && saved !== viewSelect.value) { params.set('view', saved); location.replace(`${location.pathname}?${params}`); }
    viewSelect.addEventListener('change', () => navigate(viewSelect.value));
}

document.querySelectorAll('[data-color-input]').forEach(input => {
    const preview = document.getElementById(input.dataset.colorInput);
    const update = () => { if (preview) { preview.style.backgroundColor = input.value; preview.title = input.value; } };
    input.addEventListener('input', update); update();
});

document.querySelectorAll('[data-recurrence-form]').forEach(form => {
    const select = form.querySelector('[data-recurrence-select]');
    const weekly = form.querySelector('[data-recurrence-weekly]');
    const monthly = form.querySelector('[data-recurrence-monthly]');
    const update = () => {
        weekly?.classList.toggle('hidden', select.value !== 'weekly');
        monthly?.classList.toggle('hidden', select.value !== 'monthly');
    };
    select?.addEventListener('change', update);
    if (select) update();
});

async function loadModels(select) {
    const kind = select.dataset.modelSelect, modelKind = kind === 'chat-default' ? 'chat' : kind, key = `captainslog.models.${modelKind}`, choiceKey = `captainslog.model.${modelKind}`, accountChoice = select.dataset.selected || '';
    const render = models => {
        const choice = kind === 'chat-default' ? accountChoice : (localStorage.getItem(choiceKey) || accountChoice);
        select.replaceChildren(...models.map(model => new Option(model.name || model.id, model.id)));
        if (!models.length) select.add(new Option('No compatible models available', ''));
        if (choice && [...select.options].some(option => option.value === choice)) select.value = choice;
    };
    const cached = JSON.parse(localStorage.getItem(key) || '[]'); if (cached.length) render(cached);
    try { const body = await ajax(`${select.dataset.modelsUrl}?images=${modelKind === 'image' ? 1 : 0}`); const models = body.data || []; localStorage.setItem(key, JSON.stringify(models)); render(models); }
    catch(error) { if (!cached.length) { select.replaceChildren(new Option(accountChoice || 'Add an API key in Settings', accountChoice)); } }
    if (kind !== 'chat-default') select.addEventListener('change', () => localStorage.setItem(choiceKey, select.value));
}
document.querySelectorAll('[data-model-select]').forEach(loadModels);

const requestedPanel = new URLSearchParams(location.search).get('panel');
if (['chat', 'image'].includes(requestedPanel) && document.querySelector(`[data-overlay="${requestedPanel}"]`)) {
    openOverlay(requestedPanel);
}

let activeRecording = null;

function recordingMime(type) {
    const candidates = type === 'video'
        ? ['video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']
        : ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
    return candidates.find(mime => MediaRecorder.isTypeSupported?.(mime)) || '';
}

function extensionFor(mime, type) {
    if (mime.includes('mp4')) return type === 'audio' ? 'm4a' : 'mp4';
    if (mime.includes('ogg')) return 'ogg';
    return 'webm';
}

function recordingUi(button) {
    const panel = button.closest('[data-recorder-panel]') || button.parentElement;
    return {
        panel,
        status: panel?.querySelector('[data-recording-status]'),
        message: panel?.querySelector('[data-recording-message]'),
        time: panel?.querySelector('[data-recording-time]'),
        dot: panel?.querySelector('[data-recording-dot]'),
        preview: panel?.querySelector('[data-recording-preview]'),
        buttons: [...(panel?.querySelectorAll('[data-record]') || [])],
    };
}

function setRecordingStatus(ui, message, state = 'working') {
    if (!ui.status) return;
    ui.status.classList.remove('hidden', 'border-rose-300', 'bg-rose-50', 'text-rose-800', 'border-emerald-300', 'bg-emerald-50', 'text-emerald-800', 'dark:bg-rose-950', 'dark:text-rose-200', 'dark:border-rose-800', 'dark:bg-emerald-950', 'dark:text-emerald-200', 'dark:border-emerald-800');
    if (state === 'error') ui.status.classList.add('border-rose-300', 'bg-rose-50', 'text-rose-800', 'dark:border-rose-800', 'dark:bg-rose-950', 'dark:text-rose-200');
    if (state === 'success') ui.status.classList.add('border-emerald-300', 'bg-emerald-50', 'text-emerald-800', 'dark:border-emerald-800', 'dark:bg-emerald-950', 'dark:text-emerald-200');
    ui.message.textContent = message;
    ui.dot?.classList.toggle('animate-pulse', state === 'recording');
    ui.dot?.classList.toggle('bg-rose-500', state === 'recording' || state === 'error');
    ui.dot?.classList.toggle('bg-emerald-500', state === 'success');
}

function recordingError(error, type) {
    if (!window.isSecureContext) return 'Recording requires HTTPS or a localhost address.';
    if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') return `This browser cannot record ${type}. Use the upload button instead.`;
    if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') return `${type === 'video' ? 'Camera and microphone' : 'Microphone'} permission was denied. Allow it in this site's browser settings, then try again.`;
    if (error?.name === 'NotFoundError') return `No ${type === 'video' ? 'camera or microphone' : 'microphone'} was found.`;
    if (error?.name === 'NotReadableError') return `The ${type === 'video' ? 'camera or microphone is' : 'microphone is'} busy in another application.`;
    return `Could not start ${type} recording. Use the upload button or check browser permissions.`;
}

async function uploadRecording(session) {
    const {button, chunks, mime, stream, timer, ui, type} = session;
    clearInterval(timer);
    stream.getTracks().forEach(track => track.stop());
    if (ui.preview) { ui.preview.pause(); ui.preview.srcObject = null; ui.preview.classList.add('hidden'); }
    setRecordingStatus(ui, 'Preparing recording for upload…');
    try {
        const blob = new Blob(chunks, {type: mime || (type === 'video' ? 'video/webm' : 'audio/webm')});
        if (!blob.size) throw new Error('The recording was empty.');
        const input = document.querySelector(button.dataset.target), form = input?.closest('form');
        if (!form) throw new Error('The upload form is unavailable.');
        const file = new File([blob], `${type}-${new Date().toISOString().replace(/[:.]/g, '-')}.${extensionFor(blob.type, type)}`, {type: blob.type});
        const formData = new FormData(form); formData.set('file', file);
        setRecordingStatus(ui, `Uploading ${type}…`);
        const body = await ajax(form.action, {method:'POST', body:formData});
        setRecordingStatus(ui, `${type[0].toUpperCase() + type.slice(1)} uploaded.`, 'success');
        toast(body.message || `${type} attached.`);
        if (body.reload) setTimeout(() => location.reload(), 350);
    } catch (error) {
        setRecordingStatus(ui, error.message || `Could not upload ${type}.`, 'error'); toast(error.message, true);
    } finally {
        button.textContent = button.dataset.idleLabel; button.disabled = false;
        ui.buttons.forEach(item => item.disabled = false); activeRecording = null;
    }
}

document.querySelectorAll('[data-record]').forEach(button => button.addEventListener('click', async () => {
    const type = button.dataset.record, ui = recordingUi(button);
    if (activeRecording?.button === button && activeRecording.recorder.state === 'recording') {
        button.disabled = true; button.textContent = 'Stopping…'; setRecordingStatus(ui, 'Stopping and preparing upload…'); activeRecording.recorder.stop(); return;
    }
    if (activeRecording) { setRecordingStatus(ui, 'Finish the current recording first.', 'error'); return; }
    button.dataset.idleLabel ||= button.textContent;
    try {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') throw new DOMException('Media capture unsupported', 'NotSupportedError');
        ui.buttons.forEach(item => item.disabled = true); button.textContent = 'Requesting permission…';
        setRecordingStatus(ui, `Requesting ${type === 'video' ? 'camera and microphone' : 'microphone'} permission…`);
        const stream = await navigator.mediaDevices.getUserMedia(type === 'video' ? {audio:true, video:{facingMode:'user'}} : {audio:true});
        const mime = recordingMime(type), recorder = new MediaRecorder(stream, mime ? {mimeType:mime} : undefined), chunks = [];
        const started = Date.now();
        const session = {button, chunks, mime: recorder.mimeType || mime, recorder, stream, timer:null, type, ui}; activeRecording = session;
        recorder.ondataavailable = event => { if (event.data?.size) chunks.push(event.data); };
        recorder.onerror = event => { setRecordingStatus(ui, event.error?.message || 'Recording failed.', 'error'); };
        recorder.onstop = () => uploadRecording(session);
        if (type === 'video' && ui.preview) { ui.preview.srcObject = stream; ui.preview.classList.remove('hidden'); await ui.preview.play().catch(() => {}); }
        recorder.start(1000); button.textContent = 'Stop & upload'; ui.buttons.forEach(item => item.disabled = item !== button); button.disabled = false;
        setRecordingStatus(ui, `${type[0].toUpperCase() + type.slice(1)} recording in progress`, 'recording');
        session.timer = setInterval(() => { const seconds = Math.floor((Date.now() - started) / 1000); if (ui.time) ui.time.textContent = `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`; }, 250);
    } catch (error) {
        const message = recordingError(error, type); button.textContent = button.dataset.idleLabel; ui.buttons.forEach(item => item.disabled = false); setRecordingStatus(ui, message, 'error'); toast(message, true); activeRecording = null;
    }
}));
