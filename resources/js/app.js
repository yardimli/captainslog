import './bootstrap';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const cloneTemplate = id => document.getElementById(id)?.content.firstElementChild.cloneNode(true);
const setButtonBusy = (button, busy) => {
    if (!button) return;
    const label = button.querySelector('[data-button-label]');
    button.disabled = busy;
    button.querySelector('[data-button-spinner]')?.classList.toggle('hidden', !busy);
    if (label) {
        button.dataset.idleLabel ||= label.textContent;
        label.textContent = busy ? (button.dataset.busyLabel || 'Working…') : button.dataset.idleLabel;
    }
};
const toast = (message, error = false) => {
    const el = cloneTemplate('toast-template'); if (!el) return;
    if (error) el.className = 'fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white shadow-xl';
    el.textContent = message; document.body.append(el); setTimeout(() => el.remove(), 3500);
};

function modal({title, message = '', options = null, initial = null, confirmText = 'Continue', cancelText = 'Cancel'}) {
    return new Promise(resolve => {
        const backdrop = cloneTemplate('modal-template'); if (!backdrop) { resolve(null); return; }
        const heading = backdrop.querySelector('[data-modal-title]'); heading.textContent = title;
        const copy = backdrop.querySelector('[data-modal-message]'); copy.textContent = message; copy.classList.toggle('hidden', !message);
        let input = null;
        if (options) {
            input = backdrop.querySelector('[data-modal-select]'); input.classList.remove('hidden');
            options.forEach(value => { const option = cloneTemplate('select-option-template'); option.value = value; option.textContent = value; input.append(option); });
        } else if (initial !== null) { input = backdrop.querySelector('[data-modal-textarea]'); input.classList.remove('hidden'); input.value = initial; }
        const cancel = backdrop.querySelector('[data-modal-cancel]'); cancel.textContent = cancelText;
        const confirm = backdrop.querySelector('[data-modal-confirm]'); confirm.textContent = confirmText;
        const close = value => { backdrop.remove(); resolve(value); };
        cancel.addEventListener('click', () => close(null)); confirm.addEventListener('click', () => close(input ? input.value : true));
        backdrop.addEventListener('click', event => { if (event.target === backdrop) close(null); });
        document.body.append(backdrop); (input || confirm).focus();
    });
}

async function ajax(url, options = {}) {
    const response = await fetch(url, {...options, headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json', ...(options.headers || {})}});
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Something went wrong.');
    return body;
}

async function refreshDayView() {
    const currentMain = document.querySelector('#page-content');
    if (!currentMain?.querySelector('#daily-log-page-container')) return false;
    const response = await fetch(window.location.href, {
        credentials: 'same-origin',
        headers: {Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-Day-View': 'main'},
    });
    if (!response.ok) throw new Error('The entry was saved, but the day view could not be refreshed.');
    const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
    const replacement = nextDocument.querySelector('#page-content');
    if (!replacement) throw new Error('The entry was saved, but the day view could not be refreshed.');
    const scrollPosition = {x: window.scrollX, y: window.scrollY};
    currentMain.replaceWith(replacement);
    initializeRefreshedMain(replacement);
    window.requestAnimationFrame(() => window.scrollTo(scrollPosition.x, scrollPosition.y));
    return true;
}

const reloadScrollKey = `captainslog.reload-scroll:${window.location.pathname}${window.location.search}`;
function reloadAtCurrentScroll() {
    try { sessionStorage.setItem(reloadScrollKey, JSON.stringify({x:window.scrollX, y:window.scrollY})); } catch (_) {}
    window.location.reload();
}

function restoreReloadScrollPosition() {
    let saved = null;
    try { saved = sessionStorage.getItem(reloadScrollKey); sessionStorage.removeItem(reloadScrollKey); } catch (_) {}
    if (!saved) return;
    try {
        const position = JSON.parse(saved);
        window.requestAnimationFrame(() => window.scrollTo(Number(position.x) || 0, Number(position.y) || 0));
    } catch (_) {}
}

restoreReloadScrollPosition();

async function refreshDayViewOrReload() {
    if (!await refreshDayView()) reloadAtCurrentScroll();
}

function startSessionKeepAlive() {
    const url = document.body.dataset.sessionKeepaliveUrl;
    if (!url) return;

    const interval = 5 * 60 * 1000;
    let lastPing = Date.now();
    let timer;

    const ping = async () => {
        if (!navigator.onLine) return;
        lastPing = Date.now();

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
            });

            if (response.status === 401 || response.status === 419) clearInterval(timer);
        } catch {
            // A temporary network failure should not interrupt the page.
        }
    };

    timer = window.setInterval(ping, interval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastPing >= interval) ping();
    });
}

startSessionKeepAlive();

document.querySelectorAll('[data-auto-dismiss]').forEach(element => window.setTimeout(() => element.remove(), 2000));

const accountDeleteDialog = document.querySelector('[data-account-delete-dialog]');
if (accountDeleteDialog?.dataset.open === 'true') accountDeleteDialog.showModal();
document.querySelector('[data-account-delete-open]')?.addEventListener('click', () => accountDeleteDialog?.showModal());
document.querySelector('[data-account-delete-close]')?.addEventListener('click', () => accountDeleteDialog?.close());

function setMobileNavigation(open) {
    const toggle = document.querySelector('[data-mobile-nav-toggle]');
    const menu = document.querySelector('[data-mobile-nav-menu]');
    if (!toggle || !menu) return;
    menu.classList.toggle('hidden', !open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
}

const colorThemes = ['light', 'paper', 'blue', 'red', 'dark'];
function applyTheme(theme) {
    const selected = colorThemes.includes(theme) ? theme : 'light';
    document.documentElement.dataset.theme = selected;
    document.documentElement.classList.toggle('dark', selected === 'dark' || selected === 'red');
    localStorage.setItem('captainslog.theme', selected);
    document.querySelectorAll('[data-theme-icon]').forEach(icon => icon.classList.toggle('hidden', icon.dataset.themeIcon !== selected));
    document.querySelectorAll('[data-theme-option]').forEach(option => {
        const active = option.dataset.themeOption === selected;
        option.setAttribute('aria-checked', active ? 'true' : 'false');
        option.querySelector('[data-theme-check]')?.classList.toggle('opacity-0', !active);
    });
}

applyTheme(document.documentElement.dataset.theme || 'light');

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
    if (root.dataset.overlay === 'composer' || root.dataset.overlaySide === 'right') panel?.classList.add('translate-x-full');
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

function openTimePicker(root) {
    const input = root.querySelector('[data-time-picker-input]');
    const anchor = root.querySelector('[data-time-picker-open]');
    const originalValue = input.value || '12:00';
    const [initialHour, initialMinute] = (input.value || '12:00').split(':').map(Number);
    let hour = initialHour, minute = Math.round(initialMinute / 5) * 5;
    if (minute === 60) { minute = 0; hour = (hour + 1) % 24; }
    let period = hour >= 12 ? 'PM' : 'AM';
    let displayHour = hour % 12 || 12;
    const backdrop = cloneTemplate('time-picker-dialog-template'); if (!backdrop) return;
    const panel = backdrop.querySelector('[data-time-dialog-panel]');
    const labels = backdrop.querySelector('[data-time-column-grid]');
    const wheels = backdrop.querySelector('[data-time-wheel-grid]');
    const periodLabel = backdrop.querySelector('[data-time-period-column]');
    const hour24 = backdrop.querySelector('[data-time-wheel-hour-24]');
    const hour12 = backdrop.querySelector('[data-time-wheel-hour-12]');
    const periodWheel = backdrop.querySelector('[data-time-wheel-period]');
    const usesTwelveHours = accountTimeFormat === '12';
    labels.classList.toggle('grid-cols-2', !usesTwelveHours); labels.classList.toggle('grid-cols-3', usesTwelveHours);
    wheels.classList.toggle('grid-cols-2', !usesTwelveHours); wheels.classList.toggle('grid-cols-3', usesTwelveHours);
    periodLabel.classList.toggle('hidden', !usesTwelveHours); periodWheel.classList.toggle('hidden', !usesTwelveHours);
    hour24.classList.toggle('hidden', usesTwelveHours); hour12.classList.toggle('hidden', !usesTwelveHours);
    const wheelControls = [];
    let dismissed = false, initializing = true;
    const updateInput = value => {
        anchor.textContent = formatClock(value);
        if (input.value === value) return;
        input.value = value;
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    };
    const makeWheel = (list, numeric, selected, choose, kind) => {
        const buttons = Array.from(list.querySelectorAll('[data-value]'));
        const values = buttons.map(button => numeric ? Number(button.dataset.value) : button.dataset.value);
        buttons.forEach((button, index) => button.addEventListener('click', () => {
            choose(values[index]);
            list.scrollTo({top:index * 48, behavior:'smooth'});
            render(true);
            if (kind === 'minute') dismiss();
        }));
        let scrollTimer;
        list.addEventListener('scroll', () => { clearTimeout(scrollTimer); scrollTimer = setTimeout(() => { if (dismissed) return; const index = Math.max(0, Math.min(values.length - 1, Math.round(list.scrollTop / 48))); choose(values[index]); list.scrollTo({top:index * 48, behavior:'smooth'}); render(!initializing); }, 80); });
        const control = {list, values, buttons, selected, update() { const current = selected(); buttons.forEach(button => { const active = String(button.dataset.value) === String(current); button.classList.toggle('text-white', active); button.classList.toggle('text-slate-800', !active); button.classList.toggle('dark:text-slate-200', !active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); }, center() { list.scrollTop = values.indexOf(selected()) * 48; }};
        wheelControls.push(control); return control;
    };
    makeWheel(usesTwelveHours ? hour12 : hour24, true, () => usesTwelveHours ? displayHour : hour, value => { if (usesTwelveHours) { displayHour = value; hour = (value % 12) + (period === 'PM' ? 12 : 0); } else hour = value; }, 'hour');
    makeWheel(backdrop.querySelector('[data-time-wheel-minute]'), true, () => minute, value => { minute = value; }, 'minute');
    if (usesTwelveHours) makeWheel(periodWheel, false, () => period, value => { period = value; hour = (displayHour % 12) + (period === 'PM' ? 12 : 0); }, 'period');
    let naturalDialogHeight;
    const positionPanel = () => {
        const margin = 12, gap = 8, anchorRect = anchor.getBoundingClientRect();
        const container = root.closest('[data-overlay-panel], .panel'), containerRect = container?.getBoundingClientRect();
        const width = Math.min(448, containerRect?.width || 448, window.innerWidth);
        panel.style.width = `${width}px`;
        const idealLeft = anchorRect.left + (anchorRect.width / 2) - (width / 2);
        panel.style.left = `${Math.max(0, Math.min(idealLeft, window.innerWidth - width))}px`;
        naturalDialogHeight ??= panel.scrollHeight;
        const naturalHeight = Math.min(naturalDialogHeight, window.innerHeight * 0.8);
        const roomBelow = window.innerHeight - anchorRect.bottom - gap;
        const opensBelow = roomBelow >= naturalHeight;
        const height = naturalHeight;
        const idealTop = opensBelow ? anchorRect.bottom + gap : anchorRect.top - height - gap;
        panel.style.top = `${Math.max(margin, Math.min(idealTop, window.innerHeight - height - margin))}px`;
        panel.dataset.placement = opensBelow ? 'below' : 'above';
    };
    const dismiss = () => { dismissed = true; window.removeEventListener('resize', positionPanel); window.removeEventListener('scroll', positionPanel, true); backdrop.remove(); };
    backdrop.querySelector('[data-time-dialog-cancel]').addEventListener('click', () => { updateInput(originalValue); dismiss(); });
    backdrop.addEventListener('click', event => { if (event.target === backdrop) dismiss(); });
    const render = (commit = false) => {
        const value = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
        backdrop.querySelector('[data-time-preview]').textContent = formatClock(value);
        wheelControls.forEach(control => control.update());
        if (commit) updateInput(value);
    };
    document.body.append(backdrop); render(); positionPanel(); window.addEventListener('resize', positionPanel); window.addEventListener('scroll', positionPanel, true); requestAnimationFrame(() => { wheelControls.forEach(control => control.center()); positionPanel(); setTimeout(() => { initializing = false; }, 120); });
}

function initTimePicker(root) {
    const input = root.querySelector('[data-time-picker-input]'), button = root.querySelector('[data-time-picker-open]'); if (!input || !button) return;
    const update = () => { button.textContent = formatClock(input.value || '12:00'); }; button.addEventListener('click', () => openTimePicker(root)); input.addEventListener('change', update); update();
}

document.querySelectorAll('[data-time-picker]').forEach(initTimePicker);

function initComposerMediaPanel(panel) {
    const update = () => { const label = panel.querySelector('[data-media-disclosure-label]'); if (label) label.textContent = panel.open ? 'Hide' : 'Show'; };
    panel.addEventListener('toggle', update); update();
}

document.querySelectorAll('[data-composer-media-panel]').forEach(initComposerMediaPanel);

function setEmojiPickerValue(picker, value, dispatch = false) {
    if (!picker) return;
    const input = picker.querySelector('[data-emoji-input]');
    const preview = picker.querySelector('[data-emoji-preview]');
    if (!input || !value) return;
    input.value = value;
    if (preview) preview.textContent = value;
    if (dispatch) {
        input.dispatchEvent(new Event('input', {bubbles:true}));
        input.dispatchEvent(new Event('change', {bubbles:true}));
    }
}

const emojiApiRequests = new Map();
function loadEmojiPage(baseUrl, params = {}) {
    const url = new URL(baseUrl, window.location.origin);
    Object.entries(params).forEach(([key, value]) => { if (value) url.searchParams.set(key, value); });
    const key = url.toString();
    if (!emojiApiRequests.has(key)) {
        emojiApiRequests.set(key, fetch(key, {headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest'}}).then(response => {
            if (!response.ok) throw new Error('The emoji library could not be loaded.');
            return response.json();
        }).catch(error => { emojiApiRequests.delete(key); throw error; }));
    }
    return emojiApiRequests.get(key);
}

function initEmojiPicker(picker) {
    if (picker.dataset.emojiInitialized === 'true') return;
    picker.dataset.emojiInitialized = 'true';
    const toggle = picker.querySelector('[data-emoji-toggle]');
    const menu = picker.querySelector('[data-emoji-menu]');
    const search = picker.querySelector('[data-emoji-search]');
    const categoryList = picker.querySelector('[data-emoji-categories]');
    const grid = picker.querySelector('[data-emoji-grid]');
    const categoryTemplate = picker.querySelector('[data-emoji-category-template]');
    const optionTemplate = picker.querySelector('[data-emoji-option-template]');
    const loading = picker.querySelector('[data-emoji-loading]');
    const loadingMessage = picker.querySelector('[data-emoji-loading-message]');
    const loadingSpinner = picker.querySelector('[data-emoji-loading-spinner]');
    const empty = picker.querySelector('[data-emoji-empty]');
    const host = picker.closest('.panel');
    let categories = [], activeCategory = '', hydrated = false, searchTimer, requestRevision = 0;
    const close = () => { menu?.classList.add('hidden'); menu?.classList.remove('flex'); host?.classList.remove('emoji-picker-host-active'); toggle?.setAttribute('aria-expanded', 'false'); };
    const setLoading = busy => {
        loading?.classList.toggle('hidden', !busy);
        loading?.classList.toggle('grid', busy);
        grid?.classList.toggle('opacity-40', busy);
        grid?.classList.toggle('pointer-events-none', busy);
        if (busy) {
            if (loadingMessage) loadingMessage.textContent = 'Loading emojis…';
            loadingSpinner?.classList.remove('hidden');
        }
        if (busy) empty?.classList.add('hidden');
    };
    const markCategory = category => {
        activeCategory = category.dataset.emojiCategory;
        categories.forEach(item => {
            const active = item === category;
            item.classList.toggle('bg-indigo-100', active); item.classList.toggle('text-indigo-700', active);
            item.classList.toggle('dark:bg-indigo-950', active); item.classList.toggle('dark:text-indigo-200', active);
            item.classList.toggle('text-slate-500', !active);
            item.setAttribute('aria-selected', active ? 'true' : 'false');
        });
    };
    const renderOptions = (emojis, revision) => {
        const nodes = (emojis || []).map(emoji => {
            const option = optionTemplate.content.firstElementChild.cloneNode(true);
            option.dataset.emojiValue = emoji.emoji;
            option.dataset.emojiName = emoji.name || emoji.slug || emoji.emoji;
            option.textContent = emoji.emoji;
            option.setAttribute('aria-label', emoji.name || emoji.slug || emoji.emoji);
            option.title = emoji.name || emoji.slug || emoji.emoji;
            option.classList.remove('hidden'); option.classList.add('flex');
            option.addEventListener('click', () => { setEmojiPickerValue(picker, option.dataset.emojiValue, true); close(); });
            return option;
        });
        window.requestAnimationFrame(() => {
            if (revision !== requestRevision) return;
            grid.replaceChildren(...nodes);
            setLoading(false);
            empty?.classList.toggle('hidden', nodes.length > 0);
        });
    };
    const requestEmojis = async params => {
        const revision = ++requestRevision;
        setLoading(true);
        try {
            const body = await loadEmojiPage(picker.dataset.emojiUrl, params);
            if (revision !== requestRevision) return;
            if (!hydrated) {
                const categoryNodes = (body.categories || []).map(group => {
                const category = categoryTemplate.content.firstElementChild.cloneNode(true);
                    category.dataset.emojiCategory = group.slug;
                category.textContent = group.name;
                    category.addEventListener('click', async () => {
                        clearTimeout(searchTimer);
                        if (search) search.value = '';
                        markCategory(category);
                        await requestEmojis({group:category.dataset.emojiCategory});
                });
                    return category;
                });
                categoryList.replaceChildren(...categoryNodes);
                categories = categoryNodes;
                hydrated = true;
            }
            const selected = categories.find(category => category.dataset.emojiCategory === (body.group || activeCategory)) || categories[0];
            if (selected && !params.q) markCategory(selected);
            renderOptions(body.emojis, revision);
        } catch (error) {
            if (revision !== requestRevision) return;
            if (loading) {
                loading.classList.remove('hidden'); loading.classList.add('grid');
                loadingSpinner?.classList.add('hidden');
                if (loadingMessage) loadingMessage.textContent = error.message;
            }
        }
    };
    toggle?.addEventListener('click', async () => {
        const opens = menu?.classList.contains('hidden');
        document.querySelectorAll('[data-emoji-menu]:not(.hidden)').forEach(other => { if (other !== menu) { other.classList.add('hidden'); other.classList.remove('flex'); const otherPicker = other.closest('[data-emoji-picker]'); otherPicker?.closest('.panel')?.classList.remove('emoji-picker-host-active'); otherPicker?.querySelector('[data-emoji-toggle]')?.setAttribute('aria-expanded', 'false'); } });
        if (!opens) { close(); return; }
        menu?.classList.remove('hidden'); menu?.classList.add('flex'); host?.classList.add('emoji-picker-host-active');
        toggle.setAttribute('aria-expanded', opens ? 'true' : 'false');
        if (opens) { if (!hydrated) await requestEmojis({}); setTimeout(() => search?.focus(), 0); }
    });
    search?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        const query = search.value.trim();
        searchTimer = window.setTimeout(() => requestEmojis(query ? {q:query} : {group:activeCategory}), 300);
    });
}

document.querySelectorAll('[data-emoji-picker]').forEach(initEmojiPicker);

function syncEventVisibilityControls(form) {
    const sticky = form.querySelector('[data-event-sticky-toggle]')?.checked === true;
    const field = form.querySelector('[data-sticky-visibility-field]');
    const toggle = form.querySelector('[data-visible-after-toggle]');
    const picker = form.querySelector('[data-visible-after-picker]');
    const input = picker?.querySelector('[data-time-picker-input]');
    field?.classList.toggle('hidden', !sticky);
    if (toggle) toggle.disabled = !sticky;
    const enabled = sticky && toggle?.checked === true;
    picker?.classList.toggle('hidden', !enabled);
    if (input) input.disabled = !enabled;
}

function configureEventDefinition(data = null) {
    const root = document.querySelector('[data-overlay="event-definition"]');
    const form = root?.querySelector('[data-event-definition-form]');
    if (!root || !form) return;
    const editing = Boolean(data?.update_url);
    form.reset();
    form.action = editing ? data.update_url : form.dataset.createAction;
    let method = form.querySelector('input[name="_method"]');
    if (editing) {
        if (!method) { method = cloneTemplate('ajax-method-template'); form.append(method); }
        method.value = 'PATCH';
    } else method?.remove();
    form.querySelector('[name="name"]').value = data?.name || '';
    setEmojiPickerValue(form.querySelector('[data-emoji-picker]'), data?.emoji || '✅');
    const color = form.querySelector('[name="color"]');
    color.value = data?.color || '#4f46e5';
    const colorPreview = document.getElementById(color.dataset.colorInput);
    if (colorPreview) { colorPreview.style.backgroundColor = color.value; colorPreview.title = color.value; }
    const recurrence = form.querySelector('[name="recurrence_type"]');
    recurrence.value = data?.recurrence_type || 'daily';
    const days = (data?.recurrence_days || []).map(Number);
    form.querySelectorAll('[name="weekdays[]"]').forEach(input => { input.checked = days.includes(Number(input.value)); });
    form.querySelector('[name="month_days_text"]').value = recurrence.value === 'monthly' ? days.join(', ') : '';
    form.querySelector('[name="options_text"]').value = data?.options_text || '';
    form.querySelector('[name="daily_default_count"]').value = data?.daily_default_count || 1;
    form.querySelector('[name="is_sticky"]').checked = Boolean(data?.is_sticky);
    const visibleAfter = form.querySelector('[name="visible_after"]');
    const visibleAfterToggle = form.querySelector('[data-visible-after-toggle]');
    visibleAfter.value = data?.visible_after || '18:00';
    visibleAfterToggle.checked = Boolean(data?.visible_after);
    visibleAfter.dispatchEvent(new Event('change', {bubbles:true}));
    syncEventVisibilityControls(form);
    form.querySelector('[data-time-slots]')?.setTimeSlotValues(data?.scheduled_times || []);
    recurrence.dispatchEvent(new Event('change', {bubbles:true}));
    root.querySelector('[data-event-definition-title]').textContent = editing ? `Edit ${data.name}` : 'Add event';
    root.querySelector('[data-event-definition-submit]').textContent = editing ? 'Save changes' : 'Create event';
    const deleteSection = root.querySelector('[data-event-definition-delete-section]');
    deleteSection?.classList.toggle('hidden', !editing);
    const deleteForm = root.querySelector('[data-event-definition-delete-form]');
    if (deleteForm) deleteForm.action = data?.delete_url || '';
    openOverlay('event-definition');
    setTimeout(() => form.querySelector('[name="name"]')?.focus(), 320);
}

document.addEventListener('click', event => {
    document.querySelectorAll('[data-emoji-picker]').forEach(picker => {
        if (!picker.contains(event.target)) {
            const menu = picker.querySelector('[data-emoji-menu]');
            menu?.classList.add('hidden');
            menu?.classList.remove('flex');
            picker.closest('.panel')?.classList.remove('emoji-picker-host-active');
            picker.querySelector('[data-emoji-toggle]')?.setAttribute('aria-expanded', 'false');
        }
    });
});

document.addEventListener('change', event => {
    const source = event.target.closest('[data-shared-time-source]'); if (!source) return;
    document.querySelectorAll(`[data-shared-time-field="${source.dataset.sharedTimeSource}"]`).forEach(field => { field.value = source.value; });
});

function initTimeSlots(editor) {
    if (editor.dataset.timeSlotsInitialized === 'true') return;
    editor.dataset.timeSlotsInitialized = 'true';
    const list = editor.querySelector('[data-time-slot-list]'), name = editor.dataset.name || 'scheduled_times[]';
    const addSlot = (value, open = false) => {
        const row = cloneTemplate('time-slot-row-template'); if (!row) return;
        const picker = row.querySelector('[data-time-picker]'), choose = row.querySelector('[data-time-picker-open]'), input = row.querySelector('[data-time-picker-input]');
        input.name = name; input.value = value;
        row.querySelector('[data-time-slot-remove]').addEventListener('click', () => row.remove());
        list.append(row); initTimePicker(picker); if (open) choose.click();
    };
    editor.setTimeSlotValues = values => { list.replaceChildren(); values.forEach(value => addSlot(value)); };
    let values = []; try { values = JSON.parse(editor.dataset.values || '[]'); } catch (_) {}
    editor.setTimeSlotValues(values);
    editor.querySelector('[data-time-slot-add]')?.addEventListener('click', () => { const date = new Date(), five = Math.ceil(date.getMinutes() / 5) * 5, hour = (date.getHours() + Math.floor(five / 60)) % 24; addSlot(`${String(hour).padStart(2,'0')}:${String(five % 60).padStart(2,'0')}`, true); });
}

document.querySelectorAll('[data-time-slots]').forEach(initTimeSlots);
document.querySelectorAll('[data-event-definition-form]').forEach(form => {
    form.querySelector('[data-event-sticky-toggle]')?.addEventListener('change', () => syncEventVisibilityControls(form));
    form.querySelector('[data-visible-after-toggle]')?.addEventListener('change', () => syncEventVisibilityControls(form));
    syncEventVisibilityControls(form);
});

function syncComposerTime() {
    const time = document.querySelector('[data-composer-time]')?.value || '';
    document.querySelectorAll('[data-composer-time-field]').forEach(field => { field.value = time; });
}

const eventAutosaveTimers = new WeakMap();
const eventAutosaveRevisions = new WeakMap();

function isEventAutosaveForm(form) {
    return form?.matches('[data-event-autosave-form]');
}

function scheduleEventAutosave(form, delay = 650) {
    if (!isEventAutosaveForm(form)) return;
    clearTimeout(eventAutosaveTimers.get(form));
    eventAutosaveTimers.set(form, setTimeout(async () => {
        const status = form.querySelector('[data-autosave-status]');
        const notes = form.querySelector('textarea[name="notes"]')?.value ?? '';
        const occurredAt = form.querySelector('[name="occurred_at"]')?.value;
        const emoji = form.querySelector('[name="emoji"]')?.value;
        const revision = (eventAutosaveRevisions.get(form) || 0) + 1;
        eventAutosaveRevisions.set(form, revision);
        if (status) { status.classList.remove('hidden', 'text-emerald-600', 'dark:text-emerald-400', 'text-rose-600', 'dark:text-rose-400'); status.classList.add('text-indigo-600', 'dark:text-indigo-400'); status.textContent = 'Saving…'; }
        try {
            const body = await ajax(form.action, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify({notes, emoji, occurred_at:occurredAt})});
            if (eventAutosaveRevisions.get(form) !== revision) return;
            if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400'); status.classList.add('text-emerald-600', 'dark:text-emerald-400'); status.textContent = 'Saved automatically.'; }
            const updatedLabel = form.closest('[data-overlay="composer"]')?.querySelector('[data-composer-updated]');
            if (updatedLabel && body.updated_time) { updatedLabel.textContent = `Updated ${body.updated_time}`; updatedLabel.classList.remove('hidden'); }
            await refreshDayView();
        } catch (error) {
            if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'text-emerald-600', 'dark:text-emerald-400'); status.classList.add('text-rose-600', 'dark:text-rose-400'); status.textContent = 'Could not save. Keep this editor open and try again.'; }
            toast(error.message, true);
        }
    }, delay));
}

function configureComposer({time, mode = 'create', kind = 'block', action = '', content = '', emoji = '📝', updated = '', hideUrl = '', deleteUrl = '', isHidden = false, hasMedia = false, media = [], location = null, blockId = ''} = {}) {
    const root = document.querySelector('[data-overlay="composer"]');
    const timeInput = root?.querySelector('[data-composer-time]');
    const form = root?.querySelector('[data-composer-note-form]');
    const textarea = root?.querySelector('[data-composer-content]');
    const updatedLabel = root?.querySelector('[data-composer-updated]');
    if (!root || !timeInput || !form || !textarea) return;
    clearTimeout(eventAutosaveTimers.get(form));
    form.dataset.eventAutosave = 'false';
    form.dataset.composerMode = mode;
    timeInput.value = time || new Date().toTimeString().slice(0, 5);
    timeInput.dispatchEvent(new Event('change', {bubbles:true}));
    form.action = mode === 'edit' ? action : form.dataset.createAction;
    let method = form.querySelector('input[name="_method"]');
    if (mode === 'edit') {
        if (!method) { method = cloneTemplate('ajax-method-template'); form.append(method); }
        method.value = 'PATCH';
    } else method?.remove();
    textarea.name = kind === 'event' ? 'notes' : 'content';
    textarea.value = content || '';
    textarea.required = mode === 'create';
    setEmojiPickerValue(form.querySelector('[data-emoji-picker]'), emoji || (kind === 'event' ? '✅' : '📝'));
    root.querySelector('[data-composer-title]').textContent = mode === 'edit' ? 'Edit log entry' : 'Add to this log';
    if (updatedLabel) { updatedLabel.textContent = updated ? `Updated ${updated}` : ''; updatedLabel.classList.toggle('hidden', mode !== 'edit' || !updated); }
    const locationPanel = root.querySelector('[data-composer-location]');
    const hasLocation = kind === 'event' && location?.latitude != null && location?.longitude != null;
    locationPanel?.classList.toggle('hidden', !hasLocation);
    if (hasLocation) {
        locationPanel.querySelector('[data-composer-location-coordinates]').textContent = `${Number(location.latitude).toFixed(5)}, ${Number(location.longitude).toFixed(5)}`;
        const accuracy = locationPanel.querySelector('[data-composer-location-accuracy]');
        accuracy.textContent = location.accuracy != null ? `Accuracy approximately ${Math.round(Number(location.accuracy))} metres` : '';
        accuracy.classList.toggle('hidden', location.accuracy == null);
    }
    root.querySelector('[data-note-heading]').textContent = kind === 'event' ? 'Event notes' : (mode === 'edit' ? 'Edit note' : 'Write a note');
    const submit = root.querySelector('[data-composer-submit]');
    submit.textContent = 'Add to log';
    submit.classList.toggle('hidden', mode === 'edit');
    const autosaveStatus = form.querySelector('[data-autosave-status]');
    if (autosaveStatus) { autosaveStatus.classList.toggle('hidden', mode !== 'edit'); autosaveStatus.textContent = mode === 'edit' ? 'Changes save when you close this panel.' : ''; }
    root.querySelector('[data-composer-cancel]')?.classList.toggle('hidden', mode !== 'edit');
    root.querySelector('[data-composer-time-now]')?.classList.toggle('hidden', mode !== 'edit');
    const entryActions = root.querySelector('[data-composer-entry-actions]');
    const visibility = root.querySelector('[data-composer-visibility]');
    const deleteButton = root.querySelector('[data-composer-delete]');
    const showVisibility = mode === 'edit' && Boolean(hideUrl);
    const showDelete = mode === 'edit' && Boolean(deleteUrl);
    const showActions = showVisibility || showDelete;
    entryActions?.classList.toggle('hidden', !showActions); entryActions?.classList.toggle('grid', showActions);
    if (visibility) { visibility.classList.toggle('hidden', !showVisibility); visibility.textContent = isHidden ? 'Restore' : 'Hide'; visibility.dataset.plannerVisibility = hideUrl; visibility.dataset.method = 'PATCH'; visibility.dataset.payload = JSON.stringify({hidden:!isHidden}); }
    if (deleteButton) { deleteButton.classList.toggle('hidden', !showDelete); deleteButton.dataset.delete = deleteUrl; }
    const mediaPanel = root.querySelector('[data-composer-media-panel]');
    if (mediaPanel) mediaPanel.open = mode === 'edit' && hasMedia;
    const existingMedia = root.querySelector('[data-composer-existing-media]');
    if (existingMedia) {
        const previews = media.map(item => {
            const preview = cloneTemplate('composer-image-preview-template');
            const image = preview?.querySelector('[data-composer-image-preview]');
            if (image) image.src = item.url;
            return preview;
        }).filter(Boolean);
        existingMedia.replaceChildren(...previews);
        existingMedia.classList.toggle('hidden', previews.length === 0);
        existingMedia.classList.toggle('grid', previews.length > 0);
    }
    root.querySelectorAll('[data-composer-block-field]').forEach(field => { field.value = mode === 'edit' ? blockId : ''; });
    const longTextContent = root.querySelector('[data-long-text-content]');
    if (longTextContent) longTextContent.value = '';
    const longTextFormat = root.querySelector('[data-composer-long-text-form] [name="format"]');
    if (longTextFormat) longTextFormat.value = 'text';
    syncComposerTime();
    form.dataset.originalContent = textarea.value;
    form.dataset.originalTime = timeInput.value;
    form.dataset.originalEmoji = form.querySelector('[name="emoji"]')?.value || '';
    openOverlay('composer');
    setTimeout(() => textarea.focus(), 320);
}

function browsingDuration(seconds) {
    const total = Math.max(0, Number(seconds) || 0);
    if (total < 60) return 'Under 1 min';
    const minutes = Math.floor(total / 60);
    return minutes < 60 ? `${minutes} min` : `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

function openBrowsingDetails(item) {
    const root = document.querySelector('[data-overlay="browsing"]');
    if (!root) return;
    root.querySelector('[data-browsing-detail-start]').textContent = `Started ${item.dataset.browsingStart}`;
    root.querySelector('[data-browsing-detail-total]').textContent = browsingDuration(item.dataset.browsingTotal);
    const rows = JSON.parse(item.dataset.browsingDomains || '[]').map(domain => {
        const row = cloneTemplate('browsing-domain-row-template');
        row.querySelector('[data-browsing-domain-name]').textContent = domain.domain;
        row.querySelector('[data-browsing-domain-time]').textContent = browsingDuration(domain.seconds);
        return row;
    });
    root.querySelector('[data-browsing-domain-list]').replaceChildren(...rows);
    openOverlay('browsing');
}

function openGithubDetails(item) {
    const root = document.querySelector('[data-overlay="github"]');
    if (!root) return;
    const events = JSON.parse(item.dataset.githubEvents || '[]');
    root.querySelector('[data-github-detail-project]').textContent = item.dataset.githubProject || 'GitHub activity';
    root.querySelector('[data-github-detail-start]').textContent = `Started ${item.dataset.githubStart}`;
    root.querySelector('[data-github-detail-count]').textContent = `${events.length} ${events.length === 1 ? 'commit' : 'commits'}`;
    const rows = events.map(event => {
        const row = cloneTemplate('github-event-row-template');
        row.querySelector('[data-github-event-time]').textContent = event.time;
        row.querySelector('[data-github-event-sha]').textContent = String(event.sha || '').slice(0, 7);
        row.querySelector('[data-github-event-message]').textContent = event.message || `Commit ${String(event.sha || '').slice(0, 7)}`;
        const link = row.querySelector('[data-github-event-link]');
        if (event.url) link.href = event.url; else link.classList.add('hidden');
        return row;
    });
    root.querySelector('[data-github-event-list]').replaceChildren(...rows);
    openOverlay('github');
}

function openGoogleCalendarDetails(item) {
    const root = document.querySelector('[data-overlay="google-calendar"]');
    if (!root) return;
    const event = JSON.parse(item.dataset.googleCalendarEvent || '{}');
    root.querySelector('[data-google-calendar-title]').textContent = event.title || 'Calendar event';
    root.querySelector('[data-google-calendar-start]').textContent = event.start || '';
    root.querySelector('[data-google-calendar-end]').textContent = event.end || '';
    root.querySelector('[data-google-calendar-end-wrap]').classList.toggle('hidden', !event.end);
    const location = root.querySelector('[data-google-calendar-location]');
    location.textContent = event.location || '';
    location.parentElement.classList.toggle('hidden', !event.location);
    const description = root.querySelector('[data-google-calendar-description]');
    description.textContent = event.description || '';
    description.parentElement.classList.toggle('hidden', !event.description);
    const link = root.querySelector('[data-google-calendar-link]');
    link.classList.toggle('hidden', !event.url);
    if (event.url) link.href = event.url;
    openOverlay('google-calendar');
}

function openImagePreview(trigger) {
    let root = document.querySelector('[data-overlay="image-preview"]');
    if (!root) {
        root = cloneTemplate('image-preview-overlay-template');
        document.body.append(root);
    }

    const url = trigger.dataset.imageUrl;
    const name = trigger.dataset.imageName || 'image';
    const image = root.querySelector('[data-image-preview-image]');
    const download = root.querySelector('[data-image-preview-download]');
    image.src = url;
    image.alt = name;
    download.href = url;
    download.download = name;
    openOverlay('image-preview');
}

async function saveComposerDraft(root, {close = true, refresh = true} = {}) {
    const form = root?.querySelector('[data-composer-note-form]');
    if (!form || form.dataset.composerMode !== 'edit') {
        if (close) closeOverlay(root);
        return true;
    }
    if (form.dataset.saving === 'true') return false;
    const status = form.querySelector('[data-autosave-status]');
    const textarea = form.querySelector('[data-composer-content]');
    const occurredAt = form.querySelector('[name="occurred_at"]')?.value;
    const emoji = form.querySelector('[name="emoji"]')?.value || '';
    if (textarea.value === form.dataset.originalContent && occurredAt === form.dataset.originalTime && emoji === form.dataset.originalEmoji) {
        if (close) closeOverlay(root);
        return true;
    }
    const payload = {[textarea.name]: textarea.value, emoji, occurred_at: occurredAt};
    form.dataset.saving = 'true';
    if (status) { status.classList.remove('hidden', 'text-emerald-600', 'dark:text-emerald-400', 'text-rose-600', 'dark:text-rose-400'); status.classList.add('text-indigo-600', 'dark:text-indigo-400'); status.textContent = 'Saving…'; }
    try {
        const body = await ajax(form.action, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
        if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400'); status.classList.add('text-emerald-600', 'dark:text-emerald-400'); status.textContent = 'Saved.'; }
        const updatedLabel = root.querySelector('[data-composer-updated]');
        if (updatedLabel && body.updated_time) { updatedLabel.textContent = `Updated ${body.updated_time}`; updatedLabel.classList.remove('hidden'); }
        form.dataset.originalContent = textarea.value;
        form.dataset.originalTime = occurredAt;
        form.dataset.originalEmoji = emoji;
        if (close) closeOverlay(root);
        if (refresh) await refreshDayViewOrReload();
        return true;
    } catch (error) {
        if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400', 'text-emerald-600', 'dark:text-emerald-400'); status.classList.add('text-rose-600', 'dark:text-rose-400'); status.textContent = 'Could not save. Try closing again.'; }
        toast(error.message, true);
        return false;
    } finally {
        form.dataset.saving = 'false';
    }
}

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-ajax]'); if (!form) return;
    if (isEventAutosaveForm(form)) { e.preventDefault(); scheduleEventAutosave(form, 0); return; }
    e.preventDefault(); const button = form.querySelector('[type=submit]'); setButtonBusy(button, true);
    try {
        const composer = form.closest('[data-overlay="composer"]');
        if (composer && form.matches('[data-composer-media-form], [data-composer-long-text-form]') && !await saveComposerDraft(composer, {close:false, refresh:false})) return;
        const body = await ajax(form.action, {method: form.method || 'POST', body: new FormData(form)});
        toast(body.message || 'Saved.');
        if (await refreshDayView()) {
            const overlay = form.closest('[data-overlay]');
            if (overlay) closeOverlay(overlay);
        } else if (body.reload || form.matches('[data-composer-note-form]')) reloadAtCurrentScroll();
        else form.reset();
    }
    catch (error) { toast(error.message, true); } finally { setButtonBusy(button, false); }
});

document.querySelectorAll('[data-sensor-enable]').forEach(toggle => toggle.addEventListener('change', () => toggle.form?.requestSubmit()));

document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-confirm-sensor-unlink]');
    if (!form || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    const confirmed = await modal({title:'Unlink GitHub?', message:'The encrypted token and sensor settings will be removed. Existing GitHub log entries will remain.', confirmText:'Unlink'});
    if (confirmed) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
});

document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-confirm-browser-unlink]');
    if (!form || form.dataset.confirmed === 'true') return;
    event.preventDefault();
    const confirmed = await modal({title:'Unlink Chrome extension?', message:'The extension key will stop working. Existing browsing entries and domain totals will remain.', confirmText:'Unlink'});
    if (confirmed) { form.dataset.confirmed = 'true'; form.requestSubmit(); }
});

document.addEventListener('submit', event => {
    const form = event.target.closest('[data-event-autosave-form]'); if (!form) return;
    event.preventDefault(); scheduleEventAutosave(form, 0);
});

document.addEventListener('input', event => {
    const form = event.target.closest('[data-event-autosave-form]');
    if (form && event.target.matches('textarea[name="notes"]')) scheduleEventAutosave(form);
});

document.addEventListener('change', event => {
    const form = event.target.closest('[data-event-autosave-form]');
    if (form && event.target.matches('[name="occurred_at"], [name="emoji"]')) scheduleEventAutosave(form, 0);
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
    setButtonBusy(button, true);
    const showResult = name => { result.classList.remove('hidden'); result.querySelectorAll('[data-chat-view]').forEach(view => view.classList.toggle('hidden', view.dataset.chatView !== name)); };
    showResult('status');
    try {
        const body = await ajax(form.action, {method:'POST', body:new FormData(form)});
        if (body.kind === 'answer') {
            result.querySelector('[data-chat-answer]').textContent = body.answer; showResult('answer'); form.querySelector('textarea[name=message]').value = '';
        } else if (body.kind === 'action') {
            const summary = result.querySelector('[data-chat-summary]'); summary.textContent = body.summary;
            const cancel = result.querySelector('[data-chat-cancel]'), confirm = result.querySelector('[data-chat-confirm]');
            cancel.onclick = () => result.classList.add('hidden');
            confirm.onclick = async () => {
                confirm.disabled = true; confirm.textContent = 'Applying...';
                try { const confirmed = await ajax(body.confirm_url, {method:'POST'}); toast(confirmed.message || 'Actions completed.'); if (confirmed.reload) { await refreshDayViewOrReload(); closeOverlay(form.closest('[data-overlay]')); } }
                catch (error) { toast(error.message, true); confirm.disabled = false; confirm.textContent = 'Confirm & run'; }
            };
            confirm.disabled = false; confirm.textContent = 'Confirm & run'; showResult('action');
        }
        toast(body.message || 'Chat response ready.');
    } catch (error) {
        result.querySelector('[data-chat-error]').textContent = error.message; showResult('error'); toast(error.message, true);
    } finally { setButtonBusy(button, false); }
});

function captureBrowserLocation() {
    if (!window.isSecureContext || !navigator.geolocation) return Promise.resolve(null);
    return new Promise(resolve => navigator.geolocation.getCurrentPosition(
        position => resolve({latitude:position.coords.latitude, longitude:position.coords.longitude, accuracy:position.coords.accuracy}),
        () => resolve(null),
        {enableHighAccuracy:true, timeout:10000, maximumAge:60000},
    ));
}

document.addEventListener('click', async e => {
    document.querySelectorAll('[data-events-menu][open]').forEach(menu => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
    document.querySelectorAll('[data-theme-menu][open]').forEach(menu => {
        if (!menu.contains(e.target)) menu.removeAttribute('open');
    });
    const mobileToggle = e.target.closest('[data-mobile-nav-toggle]');
    if (mobileToggle) setMobileNavigation(mobileToggle.getAttribute('aria-expanded') !== 'true');
    else if (!e.target.closest('[data-mobile-nav-menu]')) setMobileNavigation(false);
    const overlayTrigger = e.target.closest('[data-panel-open]');
    const composerTrigger = e.target.closest('[data-composer-open]');
    const eventDefinitionCreate = e.target.closest('[data-event-definition-create]');
    const eventDefinitionOpen = e.target.closest('[data-event-definition-open]');
    const imagePreview = e.target.closest('[data-image-preview-open]');
    const overlayClose = e.target.closest('[data-overlay-close]');
    const composerCancel = e.target.closest('[data-composer-cancel]');
    if (overlayTrigger) { setMobileNavigation(false); openOverlay(overlayTrigger.dataset.panelOpen); }
    if (composerTrigger) configureComposer({time: composerTrigger.dataset.currentTime || composerTrigger.dataset.defaultTime});
    if (eventDefinitionCreate) configureEventDefinition();
    if (eventDefinitionOpen) {
        const source = document.getElementById(eventDefinitionOpen.dataset.eventDefinitionOpen);
        if (source) configureEventDefinition(JSON.parse(source.textContent));
    }
    if (imagePreview) openImagePreview(imagePreview);
    if (composerCancel) closeOverlay(composerCancel.closest('[data-overlay="composer"]'));
    if (overlayClose) {
        const overlay = overlayClose.closest('[data-overlay]');
        if (overlay?.dataset.overlay === 'composer') await saveComposerDraft(overlay);
        else closeOverlay(overlay);
    }
    const themeOption = e.target.closest('[data-theme-option]');
    if (themeOption) {
        applyTheme(themeOption.dataset.themeOption);
        themeOption.closest('[data-theme-menu]')?.removeAttribute('open');
    }
    const timelineItem = e.target.closest('.timeline-item');
    const nestedAction = e.target.closest('button, a, input, textarea, select, form, audio, video');
    if (timelineItem && !composerTrigger && !e.target.closest('[data-task-event]') && (!nestedAction || nestedAction === timelineItem)) {
        if (timelineItem.matches('[data-timeline-github]')) openGithubDetails(timelineItem);
        else if (timelineItem.matches('[data-timeline-browsing]')) openBrowsingDetails(timelineItem);
        else if (timelineItem.matches('[data-timeline-google-calendar]')) openGoogleCalendarDetails(timelineItem);
        else if (timelineItem.matches('[data-timeline-edit]')) configureComposer({time: timelineItem.dataset.timelineTime, mode:'edit', kind:timelineItem.dataset.editKind, action:timelineItem.dataset.editUrl, content:timelineItem.dataset.editContent, emoji:timelineItem.dataset.editEmoji, updated:timelineItem.dataset.editUpdated, hideUrl:timelineItem.dataset.hideUrl, deleteUrl:timelineItem.dataset.deleteUrl, isHidden:timelineItem.dataset.isHidden === 'true', hasMedia:timelineItem.dataset.hasMedia === 'true', media:JSON.parse(timelineItem.dataset.editMedia || '[]'), location:JSON.parse(timelineItem.dataset.editLocation || 'null'), blockId:timelineItem.dataset.blockId});
        else if (timelineItem.matches('[data-time-gap]')) configureComposer({time:timelineItem.dataset.from});
        else configureComposer({time:timelineItem.dataset.timelineTime || timelineItem.dataset.currentTime});
    }
    const visibility = e.target.closest('[data-planner-visibility]');
    if (visibility) {
        visibility.disabled = true;
        try {
            const overlay = visibility.closest('[data-overlay]');
            if (overlay && !await saveComposerDraft(overlay, {close:false, refresh:false})) return;
            const payload = JSON.parse(visibility.dataset.payload || '{}');
            const body = await ajax(visibility.dataset.plannerVisibility, {method:visibility.dataset.method || 'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
            toast(body.message);
            if (overlay) closeOverlay(overlay);
            await refreshDayViewOrReload();
        } catch (error) { toast(error.message, true); visibility.disabled = false; }
    }
    const del = e.target.closest('[data-delete]');
    if (del && await modal({title:'Delete this item?', message:'This cannot be undone.', confirmText:'Delete'})) {
        try {
            const overlay = del.closest('[data-overlay]');
            const body = await ajax(del.dataset.delete, {method:'DELETE'});
            toast(body.message);
            if (overlay) closeOverlay(overlay);
            await refreshDayViewOrReload();
        } catch(error) { toast(error.message, true); }
    }
    const edit = e.target.closest('[data-edit-block]');
    if (edit) { const content = await modal({title:'Edit log entry', message:edit.dataset.updated ? `Updated ${edit.dataset.updated}` : '', initial:edit.dataset.content || '', confirmText:'Save'}); if (content !== null) try { await ajax(edit.dataset.editBlock,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({content})}); await refreshDayViewOrReload(); } catch(error) { toast(error.message,true); } }
    const task = e.target.closest('[data-task-event]');
    if (task) {
        task.closest('[data-events-menu]')?.removeAttribute('open');
        let value = null; const options = JSON.parse(task.dataset.options || '[]');
        if (options.length) { value = await modal({title:task.dataset.name, message:'Choose a value before this event is tracked.', options, confirmText:'Track event'}); if (value === null) return; }
        const locationPromise = task.hasAttribute('data-capture-location') ? captureBrowserLocation() : Promise.resolve(null);
        task.disabled = true;
        try {
            const body = await ajax(task.dataset.taskEvent,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({value, scheduled_time:task.dataset.scheduledTime || null})});
            document.querySelectorAll(`[data-task-event="${task.dataset.taskEvent}"]`).forEach(button => {
                const count = button.querySelector('[data-count]');
                if (!count) return;
                count.textContent = button.dataset.scheduledTime ? (body.slot_counts?.[button.dataset.scheduledTime] || 0) : body.count;
            });
            toast(body.message);
            const capturedLocation = await locationPromise;
            let savedLocation = null;
            if (capturedLocation && body.location_url) {
                try {
                    const locationBody = await ajax(body.location_url, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(capturedLocation)});
                    savedLocation = locationBody.location;
                } catch (_) { toast('The event was logged, but its location could not be saved.', true); }
            }
            await refreshDayViewOrReload();
            const addNotes = body.edit_url && await modal({title:'Event tracked', message:'Would you like to attach notes, a photo, or a recording?', confirmText:'Add notes & media', cancelText:'Done'});
            if (addNotes) configureComposer({time:body.time, mode:'edit', kind:'event', action:body.edit_url, content:'', emoji:body.emoji || '✅', hideUrl:body.hide_url, deleteUrl:body.delete_url, location:savedLocation, blockId:body.block_id});
        }
        catch(error) { toast(error.message,true); } finally { task.disabled = false; }
    }
});

function initComposerTimeInput(root = document) {
    const composerTimeInput = root.querySelector('[data-composer-time]');
    composerTimeInput?.addEventListener('input', syncComposerTime);
    composerTimeInput?.addEventListener('change', syncComposerTime);
    root.querySelector('[data-composer-time-now]')?.addEventListener('click', () => {
        if (!composerTimeInput) return;
        const now = new Date();
        composerTimeInput.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        composerTimeInput.dispatchEvent(new Event('input', {bubbles:true}));
        composerTimeInput.dispatchEvent(new Event('change', {bubbles:true}));
    });
}

initComposerTimeInput();

document.addEventListener('keydown', async event => {
    if (event.key === 'Escape') {
        document.querySelectorAll('[data-events-menu][open]').forEach(menu => menu.removeAttribute('open'));
        for (const root of document.querySelectorAll('[data-overlay][data-open="true"]')) {
            if (root.dataset.overlay === 'composer') await saveComposerDraft(root);
            else closeOverlay(root);
        }
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
        const options = models.map(model => { const option = cloneTemplate('select-option-template'); option.textContent = model.name || model.id; option.value = model.id; return option; });
        if (!options.length) { const option = cloneTemplate('select-option-template'); option.textContent = 'No compatible models available'; option.value = ''; options.push(option); }
        select.replaceChildren(...options);
        if (choice && [...select.options].some(option => option.value === choice)) select.value = choice;
    };
    const cached = JSON.parse(localStorage.getItem(key) || '[]'); if (cached.length) render(cached);
    try { const body = await ajax(`${select.dataset.modelsUrl}?images=${modelKind === 'image' ? 1 : 0}`); const models = body.data || []; localStorage.setItem(key, JSON.stringify(models)); render(models); }
    catch(error) { if (!cached.length) { const option = cloneTemplate('select-option-template'); option.textContent = accountChoice || 'Add an API key in Settings'; option.value = accountChoice; select.replaceChildren(option); } }
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
        const composer = form.closest('[data-overlay="composer"]');
        if (composer && !await saveComposerDraft(composer, {close:false, refresh:false})) throw new Error('Save the entry before uploading this recording.');
        const file = new File([blob], `${type}-${new Date().toISOString().replace(/[:.]/g, '-')}.${extensionFor(blob.type, type)}`, {type: blob.type});
        const formData = new FormData(form); formData.set('file', file);
        setRecordingStatus(ui, `Uploading ${type}…`);
        const body = await ajax(form.action, {method:'POST', body:formData});
        setRecordingStatus(ui, `${type[0].toUpperCase() + type.slice(1)} uploaded.`, 'success');
        toast(body.message || `${type} attached.`);
        if (body.reload) setTimeout(async () => { await refreshDayViewOrReload(); closeOverlay(form.closest('[data-overlay]')); }, 350);
    } catch (error) {
        setRecordingStatus(ui, error.message || `Could not upload ${type}.`, 'error'); toast(error.message, true);
    } finally {
        button.textContent = button.dataset.idleLabel; button.disabled = false;
        ui.buttons.forEach(item => item.disabled = false); activeRecording = null;
    }
}

async function handleRecordingButton(button) {
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
}

function initRecordingButton(button) {
    button.addEventListener('click', () => handleRecordingButton(button));
}

document.querySelectorAll('[data-record]').forEach(initRecordingButton);

function initializeRefreshedMain(root) {
    root.querySelectorAll('[data-time-picker]').forEach(initTimePicker);
    root.querySelectorAll('[data-composer-media-panel]').forEach(initComposerMediaPanel);
    root.querySelectorAll('[data-emoji-picker]').forEach(initEmojiPicker);
    root.querySelectorAll('[data-model-select]').forEach(loadModels);
    root.querySelectorAll('[data-record]').forEach(initRecordingButton);
    initComposerTimeInput(root);
    scheduleStickyVisibilityRefresh(root);
}

let stickyVisibilityTimer;
function scheduleStickyVisibilityRefresh(root = document) {
    clearTimeout(stickyVisibilityTimer);
    const container = root.querySelector?.('[data-next-sticky-visibility]');
    const value = container?.dataset.nextStickyVisibility;
    if (!value) return;
    const [hour, minute] = value.split(':').map(Number);
    const target = new Date();
    target.setHours(hour, minute, 0, 0);
    const delay = target.getTime() - Date.now();
    if (delay <= 0) return;
    stickyVisibilityTimer = window.setTimeout(() => refreshDayViewOrReload(), delay + 500);
}

scheduleStickyVisibilityRefresh();
