import './bootstrap';

if (document.querySelector('[data-note-rich-editor]')) import('./notes');

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
    const response = await fetch(url, {credentials: 'same-origin', ...options, headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json', ...(options.headers || {})}});
    if (isExpiredSessionResponse(response)) {
        showSessionExpired();
        throw new Error('Your session has expired. Sign in again to continue.');
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'Something went wrong.');
    return body;
}

const backgroundSyncQueue = new Map();
const pendingEventCreates = new Map();

function renderBackgroundSyncStatus() {
    const label = document.querySelector('[data-sync-status]');
    if (!label) return;
    const entries = [...backgroundSyncQueue.values()];
    label.classList.toggle('hidden', entries.length === 0);
    if (!entries.length) return;
    if (entries.some(entry => entry.state === 'failed')) label.textContent = 'Changes not synced — retrying…';
    else if (entries.some(entry => entry.running)) label.textContent = 'Syncing changes…';
    else label.textContent = 'Changes waiting to sync…';
}

function scheduleSyncRun(key, entry, delay = 1000) {
    clearTimeout(entry.timer);
    entry.timer = window.setTimeout(() => runBackgroundSync(key, entry), delay);
    renderBackgroundSyncStatus();
}

async function runBackgroundSync(key, entry) {
    if (entry.running) { scheduleSyncRun(key, entry, 250); return; }
    const version = entry.version;
    const request = entry.request;
    const onSuccess = entry.onSuccess;
    entry.running = true;
    entry.state = 'syncing';
    entry.timer = null;
    renderBackgroundSyncStatus();
    try {
        const body = await request();
        if (entry.cancelled) return;
        onSuccess?.(body);
        entry.running = false;
        if (entry.version === version) backgroundSyncQueue.delete(key);
        else scheduleSyncRun(key, entry);
    } catch (error) {
        if (entry.cancelled) return;
        entry.running = false;
        entry.state = 'failed';
        entry.error = error;
        scheduleSyncRun(key, entry, 5000);
    }
    renderBackgroundSyncStatus();
}

function queueBackgroundSync(key, request, onSuccess = null) {
    const entry = backgroundSyncQueue.get(key) || {version:0, running:false, timer:null, state:'pending'};
    entry.version += 1;
    entry.request = request;
    entry.onSuccess = onSuccess;
    entry.state = 'pending';
    backgroundSyncQueue.set(key, entry);
    scheduleSyncRun(key, entry);
}

function cancelBackgroundSync(key) {
    const entry = backgroundSyncQueue.get(key);
    if (!entry) return;
    entry.cancelled = true;
    clearTimeout(entry.timer);
    backgroundSyncQueue.delete(key);
    renderBackgroundSyncStatus();
}

window.addEventListener('pagehide', () => {
    backgroundSyncQueue.forEach((entry, key) => {
        clearTimeout(entry.timer);
        runBackgroundSync(key, entry);
    });
});

const dayStateCache = new Map();
const dayStateRequests = new Map();
let activeDayState = null;
const snapshotDayState = () => activeDayState ? structuredClone(activeDayState) : null;
const restoreDayState = state => { if (state) renderDayState(state, {scroll:{x:window.scrollX, y:window.scrollY}}); };

function dayStateKey(url = window.location.href) {
    const parsed = new URL(url, window.location.href);
    return `${parsed.pathname}${parsed.search}`;
}

function captureCurrentDayState() {
    const source = document.querySelector('#day-log-state');
    if (!source) return null;
    const state = JSON.parse(source.textContent);
    dayStateCache.set(dayStateKey(state.url), state);
    activeDayState = state;
    return state;
}

function element(tag, className = '', text = null) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== null) node.textContent = text;
    return node;
}

function renderTaskButton(task, scheduledTime = null) {
    const button = element('button', 'flex min-w-0 flex-1 items-center rounded-xl px-3 py-2.5 text-left text-sm font-semibold shadow-sm transition hover:brightness-110 disabled:cursor-wait disabled:opacity-50');
    button.style.backgroundColor = task.color;
    button.style.color = task.text_color;
    button.dataset.taskEvent = task.event_url;
    if (scheduledTime) button.dataset.scheduledTime = scheduledTime;
    button.dataset.captureLocation = '';
    button.dataset.name = task.name;
    button.dataset.options = JSON.stringify(task.options || []);
    const color = element('span', 'mr-2 h-3 w-3 shrink-0 rounded-sm border border-current opacity-80'); color.style.backgroundColor = task.color;
    const emoji = element('span', 'mr-2 text-lg', task.emoji); emoji.setAttribute('aria-hidden', 'true');
    const name = element('span', 'truncate', task.name);
    const count = element('span', 'ml-2 rounded-full bg-white/20 px-2', String(scheduledTime ? task.slot_count : task.count)); count.dataset.count = '';
    button.append(color, emoji, name, count);
    return button;
}

function renderTimelineItem(item) {
    if (item.kind === 'gap') {
        if (item.state === 'past') return null;
        const row = element('div', 'timeline-item flex cursor-pointer items-center gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/20 dark:text-indigo-300');
        row.dataset.timeGap = ''; row.dataset.state = item.state; row.dataset.from = item.from; row.dataset.to = item.to;
        row.append(element('span', 'rounded-full bg-indigo-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200', 'Open'), element('span', 'h-px flex-1 bg-current opacity-20'));
        const time = element('time', 'font-mono text-xs font-bold', `${formatClock(item.from)} – ${formatClock(item.to)}`); row.append(time);
        return row;
    }
    if (item.kind === 'now') {
        const row = element('button', 'timeline-item flex w-full scroll-mt-24 items-center gap-3 py-1 text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-4 dark:text-indigo-400 dark:focus:ring-offset-slate-950');
        row.type = 'button'; row.id = 'timeline-now'; row.dataset.currentTime = item.time; row.dataset.composerOpen = ''; row.setAttribute('aria-label', 'Add to the log now');
        row.append(element('span', 'h-px flex-1 bg-current opacity-40'), element('span', 'rounded-full bg-indigo-600 px-3 py-1 text-xs font-bold text-white', `Now · ${formatClock(item.time)}`), element('span', 'h-px flex-1 bg-current opacity-40'));
        return row;
    }
    if (item.kind === 'schedule') {
        const row = element('div', 'timeline-item flex min-w-0 cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-white p-3 pl-0 shadow-sm dark:border-slate-700 dark:bg-slate-900');
        row.dataset.scheduledEvent = ''; row.dataset.timelineTime = item.time;
        row.append(element('time', 'w-20 shrink-0 text-center font-mono text-xs font-bold text-slate-500', item.is_unscheduled ? 'Any' : formatClock(item.time)), renderTaskButton(item.task, item.is_unscheduled ? null : item.time));
        return row;
    }

    const block = item.block;
    const row = element('div', `timeline-item flex min-w-0 cursor-pointer items-start gap-3 ${block.is_hidden ? 'opacity-60' : ''}`);
    row.dataset.recordedTime = item.time; row.dataset.timelineTime = item.time;
    if (block.type === 'sensor_browser') {
        row.dataset.timelineBrowsing = ''; row.dataset.browsingStart = formatClock(item.time); row.dataset.browsingDomains = JSON.stringify(block.browsing_domains || []); row.dataset.browsingTotal = String((block.browsing_domains || []).reduce((total, domain) => total + Number(domain.seconds || 0), 0));
    } else if (block.type === 'sensor_github' && (block.github_events || []).length) {
        row.dataset.timelineGithub = ''; row.dataset.githubProject = block.content || ''; row.dataset.githubStart = formatClock(item.time); row.dataset.githubEvents = JSON.stringify(block.github_events);
    } else if (block.type === 'sensor_google_calendar') {
        row.dataset.timelineGoogleCalendar = ''; row.dataset.googleCalendarEvent = JSON.stringify(block.calendar_event || {});
    } else {
        row.dataset.timelineEdit = ''; row.dataset.editKind = block.edit_kind; row.dataset.editUrl = block.edit_url; row.dataset.editContent = block.content || ''; row.dataset.editEmoji = block.emoji || ''; row.dataset.editUpdated = block.updated || ''; row.dataset.editLocation = JSON.stringify(block.event?.location || null); row.dataset.hideUrl = block.hide_url; row.dataset.deleteUrl = block.delete_url; row.dataset.isHidden = block.is_hidden ? 'true' : 'false';
    }
    if (block.is_hidden) row.dataset.hiddenPlannerItem = '';
    row.append(element('time', 'w-20 shrink-0 pt-4 text-center font-mono text-xs font-bold text-slate-500', formatClock(item.time)));
    const wrapper = element('div', 'timeline-entry-card min-w-0 flex-1');
    const article = element('article', `panel group ${block.is_hidden ? 'ring-2 ring-amber-400' : ''} ${block.optimistic ? 'ring-2 ring-indigo-300' : ''}`); article.id = `block-${block.id}`;
    const description = element('div', block.event ? 'block-event-description flex flex-wrap items-center gap-2 leading-relaxed' : 'block-text-description whitespace-pre-wrap leading-relaxed'); description.dataset.blockDescription = '';
    const emoji = element('span', block.event ? 'text-xl' : 'mr-2 inline-block align-middle text-xl', block.emoji || '📝'); emoji.dataset.blockEmoji = ''; emoji.setAttribute('aria-hidden', 'true');
    const label = element('span', `mr-2 inline-flex align-middle rounded-lg px-2 py-1 text-xs font-bold uppercase ${block.event ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`, block.type.replaceAll('_', ' ')); label.dataset.blockTypeLabel = '';
    description.append(emoji, label);
    if (block.is_hidden) description.append(element('span', 'mr-2 inline-flex align-middle rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-800', 'Hidden'));
    if (block.event) description.append(element('span', 'text-lg font-bold', `${block.event.name}${block.event.value ? ` · ${block.event.value}` : ''}`));
    else description.append(document.createTextNode(block.content || ''));
    article.append(description);
    if (block.event && block.content) article.append(element('div', 'block-event-notes mt-2 whitespace-pre-wrap leading-relaxed', block.content));
    if (block.type === 'generated_image') (block.attachments || []).forEach(attachment => {
        const frame = element('div', 'block-attachment mt-3 overflow-hidden rounded-xl bg-slate-100 dark:bg-slate-950');
        const image = element('img', 'h-[512px] max-h-[512px] w-full object-contain'); image.src = attachment.url; image.alt = 'AI-generated image'; image.loading = 'lazy';
        frame.append(image); article.append(frame);
    });
    wrapper.append(article); row.append(wrapper);
    return row;
}

function updateDayNavigation(state) {
    const date = document.querySelector('[data-navigation-date]');
    if (date) { date.textContent = state.title; date.setAttribute('datetime', state.date); }
    const links = document.querySelectorAll('[data-day-navigation] > a');
    if (links[0]) links[0].href = state.navigation.previous_url;
    if (links[1]) links[1].href = state.navigation.today_url;
    if (links[2]) links[2].href = state.navigation.next_url;
    const hiddenToggle = document.querySelector('[data-hidden-entries-toggle]');
    if (hiddenToggle) {
        hiddenToggle.href = state.show_hidden ? state.url.replace(/\?show_hidden=1$/, '') : `${state.url.split('?')[0]}?show_hidden=1`;
        hiddenToggle.textContent = state.show_hidden ? 'Hide hidden entries' : 'Show hidden entries';
    }
    const menu = document.querySelector('#more-events-menu');
    if (menu) {
        const eventsMenu = menu.closest('[data-events-menu]');
        const nextMenu = menu.cloneNode(false);
        const menuContent = document.createDocumentFragment();
        state.tasks.forEach(task => menuContent.append(renderTaskButton(task)));
        nextMenu.append(menuContent);
        menu.replaceWith(nextMenu);
        eventsMenu?.classList.toggle('hidden', state.tasks.length === 0);
    }
}

function updateDayControls(state) {
    const container = document.querySelector('#daily-log-page-container');
    if (state.next_sticky_visibility) container.dataset.nextStickyVisibility = state.next_sticky_visibility; else delete container.dataset.nextStickyVisibility;
    const timeline = document.querySelector('#timeline');
    const nextTimeline = timeline.cloneNode(false);
    const timelineContent = document.createDocumentFragment();
    state.timeline.forEach(item => {
        const rendered = renderTimelineItem(item);
        if (rendered) timelineContent.append(rendered);
    });
    nextTimeline.dataset.logDate = state.date;
    nextTimeline.append(timelineContent);
    timeline.replaceWith(nextTimeline);
    const composer = document.querySelector('[data-composer-note-form]');
    if (composer) { composer.action = state.log.create_block_url; composer.dataset.createAction = state.log.create_block_url; }
    const headingDate = document.querySelector('#log-composer-heading-copy > p'); if (headingDate) headingDate.textContent = state.title;
    const chat = document.querySelector('[data-smart-chat-form]'); if (chat) chat.action = state.log.chat_url;
    const image = document.querySelector('[data-overlay="image"] form'); if (image) image.action = state.log.image_url;
    updateDayNavigation(state);
}

function renderDayState(state, {scroll = null} = {}) {
    if (!document.querySelector('#daily-log-page-container')) return false;
    activeDayState = state;
    dayStateCache.set(dayStateKey(state.url), state);
    updateDayControls(state);
    scheduleStickyVisibilityRefresh();
    if (scroll) window.requestAnimationFrame(() => window.scrollTo(scroll.x, scroll.y));
    return true;
}

function mutateDayState(mutator) {
    const state = activeDayState || captureCurrentDayState();
    if (!state) return false;
    mutator(state);
    return renderDayState(state, {scroll:{x:window.scrollX, y:window.scrollY}});
}

async function fetchDayState(url, {fresh = false} = {}) {
    const key = dayStateKey(url);
    if (!fresh && dayStateCache.has(key)) return dayStateCache.get(key);
    if (dayStateRequests.has(key)) return dayStateRequests.get(key);
    const request = fetch(url, {
            credentials: 'same-origin',
            headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-Day-State': 'json'},
        })
        .then(async response => {
            if (isExpiredSessionResponse(response)) { showSessionExpired(); throw new Error('Your session has expired.'); }
            if (!response.ok) throw new Error('The day could not be loaded.');
            const state = await response.json();
            dayStateCache.set(key, state);
            return state;
        })
        .finally(() => dayStateRequests.delete(key));
    dayStateRequests.set(key, request);
    return request;
}

async function navigateToDay(url, {history = true, fresh = false} = {}) {
    const key = dayStateKey(url);
    const cached = !fresh ? dayStateCache.get(key) : null;
    if (cached) {
        if (history) window.history.pushState({dayState:true}, '', cached.url || url);
        renderDayState(cached);
        window.scrollTo(0, 0);
        return true;
    }
    const state = await fetchDayState(url, {fresh:true});
    if (history) window.history.pushState({dayState:true}, '', state.url || url);
    renderDayState(state);
    window.scrollTo(0, 0);
    return true;
}

async function refreshDayView() {
    if (!document.querySelector('#daily-log-page-container')) return false;
    const state = await fetchDayState(window.location.href, {fresh:true});
    return renderDayState(state, {scroll:{x:window.scrollX, y:window.scrollY}});
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

function isExpiredSessionResponse(response) {
    const redirectedToLogin = response.redirected && new URL(response.url, window.location.href).pathname.includes('/login');
    return response.status === 401 || response.status === 419 || redirectedToLogin;
}

function showSessionExpired() {
    const overlay = document.querySelector('[data-session-expired-overlay]');
    if (!overlay || overlay.dataset.open === 'true') return;
    overlay.dataset.open = 'true';
    overlay.classList.remove('hidden');
    overlay.classList.add('grid');
    document.body.classList.add('overflow-hidden');
    overlay.querySelector('[data-session-login]')?.focus();
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

            if (isExpiredSessionResponse(response)) {
                clearInterval(timer);
                showSessionExpired();
            }
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
captureCurrentDayState();

document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || !/^\/logs\/\d{4}-\d{2}-\d{2}$/.test(url.pathname)) return;
    event.preventDefault();
    navigateToDay(url.href).catch(error => toast(error.message, true));
});

document.addEventListener('pointerenter', event => {
    const link = event.target.closest?.('a[href]');
    if (!link) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin === window.location.origin && /^\/logs\/\d{4}-\d{2}-\d{2}$/.test(url.pathname)) fetchDayState(url.href).catch(() => {});
}, true);

window.addEventListener('popstate', () => {
    if (/^\/logs\/\d{4}-\d{2}-\d{2}$/.test(window.location.pathname)) navigateToDay(window.location.href, {history:false}).catch(() => window.location.reload());
});

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
    const periodColumn = backdrop.querySelector('[data-time-wheel-period-column]');
    const usesTwelveHours = accountTimeFormat === '12';
    labels.classList.toggle('grid-cols-2', !usesTwelveHours); labels.classList.toggle('grid-cols-3', usesTwelveHours);
    wheels.classList.toggle('grid-cols-2', !usesTwelveHours); wheels.classList.toggle('grid-cols-3', usesTwelveHours);
    periodLabel.classList.toggle('hidden', !usesTwelveHours); periodColumn.classList.toggle('hidden', !usesTwelveHours);
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
        const stepButtons = Array.from(list.closest('[data-time-wheel-column]').querySelectorAll('[data-time-wheel-step]'));
        const selectIndex = index => {
            const next = Math.max(0, Math.min(values.length - 1, index));
            choose(values[next]);
            list.scrollTop = next * 48;
            render(true);
        };
        buttons.forEach((button, index) => button.addEventListener('click', () => {
            selectIndex(index);
            if (kind === 'minute') dismiss();
        }));
        stepButtons.forEach(button => button.addEventListener('click', () => selectIndex(values.indexOf(selected()) + Number(button.dataset.timeWheelStep))));
        let scrollTimer;
        let lastWheelAt = 0;
        list.addEventListener('wheel', event => {
            event.preventDefault();
            const now = performance.now();
            if (now - lastWheelAt < 40 || !event.deltaY) return;
            lastWheelAt = now;
            const current = Math.round(list.scrollTop / 48);
            const index = Math.max(0, Math.min(values.length - 1, current + Math.sign(event.deltaY)));
            choose(values[index]);
            list.scrollTop = index * 48;
            render(true);
        }, {passive:false});
        list.addEventListener('scroll', () => { clearTimeout(scrollTimer); scrollTimer = setTimeout(() => { if (dismissed) return; const index = Math.max(0, Math.min(values.length - 1, Math.round(list.scrollTop / 48))); choose(values[index]); list.scrollTop = index * 48; render(!initializing); }, 40); });
        const control = {list, values, buttons, selected, update() { const current = selected(), index = values.indexOf(current); buttons.forEach(button => { const active = String(button.dataset.value) === String(current); button.classList.toggle('text-white', active); button.classList.toggle('text-slate-800', !active); button.classList.toggle('dark:text-slate-200', !active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); stepButtons.forEach(button => { const disabled = Number(button.dataset.timeWheelStep) < 0 ? index <= 0 : index >= values.length - 1; button.disabled = disabled; button.classList.toggle('opacity-30', disabled); }); }, center() { list.scrollTop = values.indexOf(selected()) * 48; }};
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

function isEventAutosaveForm(form) {
    return form?.matches('[data-event-autosave-form]');
}

function scheduleEventAutosave(form, delay = 650) {
    if (!isEventAutosaveForm(form)) return;
    clearTimeout(eventAutosaveTimers.get(form));
    eventAutosaveTimers.set(form, setTimeout(() => {
        const status = form.querySelector('[data-autosave-status]');
        const notes = form.querySelector('textarea[name="notes"]')?.value ?? '';
        const occurredAt = form.querySelector('[name="occurred_at"]')?.value;
        const emoji = form.querySelector('[name="emoji"]')?.value;
        if (status) { status.classList.remove('hidden', 'text-emerald-600', 'dark:text-emerald-400', 'text-rose-600', 'dark:text-rose-400'); status.classList.add('text-indigo-600', 'dark:text-indigo-400'); status.textContent = 'Queued for sync.'; }
        updateLocalTimelineBlock(form.action, {content:notes, emoji, time:occurredAt});
        const action = form.action;
        queueBackgroundSync(`event-edit:${action}`, () => ajax(action, {method:'PATCH', keepalive:true, headers:{'Content-Type':'application/json'}, body:JSON.stringify({notes, emoji, occurred_at:occurredAt})}), body => {
            if (status) { status.classList.remove('text-indigo-600', 'dark:text-indigo-400'); status.classList.add('text-emerald-600', 'dark:text-emerald-400'); status.textContent = 'Saved automatically.'; }
            const updatedLabel = form.closest('[data-overlay="composer"]')?.querySelector('[data-composer-updated]');
            if (updatedLabel && body.updated_time) { updatedLabel.textContent = `Updated ${body.updated_time}`; updatedLabel.classList.remove('hidden'); }
        });
    }, delay));
}

function configureComposer({time, mode = 'create', kind = 'block', action = '', content = '', emoji = '📝', updated = '', hideUrl = '', deleteUrl = '', isHidden = false, location = null, pendingEventId = ''} = {}) {
    const root = document.querySelector('[data-overlay="composer"]');
    const timeInput = root?.querySelector('[data-composer-time]');
    const form = root?.querySelector('[data-composer-note-form]');
    const textarea = root?.querySelector('[data-composer-content]');
    const updatedLabel = root?.querySelector('[data-composer-updated]');
    if (!root || !timeInput || !form || !textarea) return;
    clearTimeout(eventAutosaveTimers.get(form));
    form.dataset.eventAutosave = 'false';
    form.dataset.composerMode = mode;
    if (pendingEventId) form.dataset.pendingEventId = pendingEventId; else delete form.dataset.pendingEventId;
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

function findBlockItem(state, url, field = 'edit_url') {
    return state.timeline.find(item => item.kind === 'block' && item.block?.[field] === url);
}

function findPendingBlockItem(state, pendingEventId) {
    return state.timeline.find(item => item.kind === 'block' && item.block?.client_id === pendingEventId);
}

function addOptimisticTimelineBlock(state, {id, time, emoji, content, kind = 'text', editUrl = '', hideUrl = '', deleteUrl = '', eventName = '', selectedValue = ''}) {
    const item = {
        kind:'block', time,
        block:{
            id, client_id:id, type:kind === 'event' ? 'event' : 'text', emoji, content, is_hidden:false, updated:'syncing', optimistic:true,
            edit_kind:kind === 'event' ? 'event' : 'block', edit_url:editUrl, hide_url:hideUrl, delete_url:deleteUrl,
            event:kind === 'event' ? {name:eventName, value:selectedValue || null, location:null} : null,
            attachments:[], browsing_domains:[], github_events:[], calendar_event:null,
        },
    };
    const followingIndex = state.timeline.findIndex(candidate => ['block', 'schedule', 'now'].includes(candidate.kind) && (candidate.time || '24:00') > time);
    state.timeline.splice(followingIndex < 0 ? state.timeline.length : followingIndex, 0, item);
}

function updateLocalTimelineBlock(url, {content, emoji, time}, pendingEventId = '') {
    return mutateDayState(state => {
        const item = pendingEventId ? findPendingBlockItem(state, pendingEventId) : findBlockItem(state, url);
        if (!item) return;
        if (content !== undefined) item.block.content = content;
        if (emoji) item.block.emoji = emoji;
        if (time) item.time = time;
    });
}

function reconcileOptimisticBlock(id, body) {
    const item = activeDayState?.timeline.find(candidate => candidate.kind === 'block' && candidate.block?.id === id);
    if (!item) return;
    item.block.id = body.block?.id || body.block_id || item.block.id;
    item.block.edit_url = body.edit_url || item.block.edit_url;
    item.block.hide_url = body.hide_url || item.block.hide_url;
    item.block.delete_url = body.delete_url || item.block.delete_url;
    item.block.updated = body.updated_time || '';
    item.block.optimistic = false;
    const row = document.querySelector(`#block-${CSS.escape(String(id))}`)?.closest('.timeline-item');
    if (row) {
        row.querySelector('article').id = `block-${item.block.id}`;
        row.querySelector('article').classList.remove('ring-2', 'ring-indigo-300');
        row.dataset.editUrl = item.block.edit_url; row.dataset.hideUrl = item.block.hide_url; row.dataset.deleteUrl = item.block.delete_url; row.dataset.editUpdated = item.block.updated;
    }
}

function saveComposerDraft(root, {close = true} = {}) {
    const form = root?.querySelector('[data-composer-note-form]');
    if (!form || form.dataset.composerMode !== 'edit') {
        if (close) closeOverlay(root);
        return true;
    }
    const status = form.querySelector('[data-autosave-status]');
    const textarea = form.querySelector('[data-composer-content]');
    const occurredAt = form.querySelector('[name="occurred_at"]')?.value;
    const emoji = form.querySelector('[name="emoji"]')?.value || '';
    if (textarea.value === form.dataset.originalContent && occurredAt === form.dataset.originalTime && emoji === form.dataset.originalEmoji) {
        if (close) closeOverlay(root);
        return true;
    }
    const payload = {[textarea.name]: textarea.value, emoji, occurred_at: occurredAt};
    const action = form.action;
    const pendingEventId = form.dataset.pendingEventId || '';
    const state = activeDayState;
    if (close) closeOverlay(root);
    updateLocalTimelineBlock(action, {content:textarea.value, emoji, time:occurredAt}, pendingEventId);
    form.dataset.originalContent = textarea.value;
    form.dataset.originalTime = occurredAt;
    form.dataset.originalEmoji = emoji;
    if (status) { status.classList.remove('hidden', 'text-emerald-600', 'dark:text-emerald-400'); status.classList.add('text-indigo-600', 'dark:text-indigo-400'); status.textContent = 'Queued for sync.'; }
    const syncKey = pendingEventId ? `pending-event-edit:${pendingEventId}` : `log-edit:${action}`;
    const request = pendingEventId
        ? async () => {
            const created = await pendingEventCreates.get(pendingEventId);
            return ajax(created.edit_url, {method:'PATCH', keepalive:true, headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
        }
        : () => ajax(action, {method:'PATCH', keepalive:true, headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
    queueBackgroundSync(syncKey, request, body => {
        const item = state ? (pendingEventId ? findPendingBlockItem(state, pendingEventId) : findBlockItem(state, action)) : null;
        if (item && body.updated_time) item.block.updated = body.updated_time;
        if (activeDayState === state) {
            const row = [...document.querySelectorAll('[data-edit-url]')].find(candidate => candidate.dataset.editUrl === action);
            if (row && body.updated_time) row.dataset.editUpdated = body.updated_time;
        }
        if (pendingEventId) pendingEventCreates.delete(pendingEventId);
    });
    return true;
}

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-ajax]'); if (!form) return;
    if (isEventAutosaveForm(form)) { e.preventDefault(); scheduleEventAutosave(form, 0); return; }
    e.preventDefault();
    if (form.matches('[data-composer-note-form]') && form.dataset.composerMode !== 'edit') {
        const requestBody = new FormData(form);
        const action = form.action;
        const previousState = snapshotDayState();
        const optimisticId = `local-${crypto.randomUUID?.() || Date.now()}`;
        const draft = {
            id:optimisticId,
            time:String(requestBody.get('occurred_at') || '12:00'),
            emoji:String(requestBody.get('emoji') || '📝'),
            content:String(requestBody.get('content') || ''),
            editUrl:action,
        };
        mutateDayState(state => addOptimisticTimelineBlock(state, draft));
        closeOverlay(document.querySelector('[data-overlay="composer"]'));
        try {
            const body = await ajax(action, {method:'POST', body:requestBody});
            reconcileOptimisticBlock(optimisticId, body);
            toast(body.message || 'Saved.');
        } catch (error) {
            restoreDayState(previousState);
            toast(error.message, true);
        }
        return;
    }
    const button = form.querySelector('[type=submit]'); setButtonBusy(button, true);
    try {
        const composer = form.closest('[data-overlay="composer"]');
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
        if (overlay?.dataset.overlay === 'composer') saveComposerDraft(overlay);
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
        else if (timelineItem.matches('[data-timeline-edit]')) configureComposer({time: timelineItem.dataset.timelineTime, mode:'edit', kind:timelineItem.dataset.editKind, action:timelineItem.dataset.editUrl, content:timelineItem.dataset.editContent, emoji:timelineItem.dataset.editEmoji, updated:timelineItem.dataset.editUpdated, hideUrl:timelineItem.dataset.hideUrl, deleteUrl:timelineItem.dataset.deleteUrl, isHidden:timelineItem.dataset.isHidden === 'true', location:JSON.parse(timelineItem.dataset.editLocation || 'null')});
        else if (timelineItem.matches('[data-time-gap]')) configureComposer({time:timelineItem.dataset.from});
        else configureComposer({time:timelineItem.dataset.timelineTime || timelineItem.dataset.currentTime});
    }
    const visibility = e.target.closest('[data-planner-visibility]');
    if (visibility) {
        visibility.disabled = true;
        const previousState = snapshotDayState();
        try {
            const overlay = visibility.closest('[data-overlay]');
            if (overlay && !saveComposerDraft(overlay, {close:false})) return;
            const payload = JSON.parse(visibility.dataset.payload || '{}');
            const visibilityUrl = visibility.dataset.plannerVisibility;
            mutateDayState(state => {
                const item = findBlockItem(state, visibilityUrl, 'hide_url');
                if (!item) return;
                if (!state.show_hidden && payload.hidden) state.timeline = state.timeline.filter(candidate => candidate !== item);
                else item.block.is_hidden = Boolean(payload.hidden);
            });
            const body = await ajax(visibility.dataset.plannerVisibility, {method:visibility.dataset.method || 'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
            toast(body.message);
        } catch (error) {
            restoreDayState(previousState);
            toast(error.message, true);
        }
    }
    const del = e.target.closest('[data-delete]');
    if (del && await modal({title:'Delete this item?', message:'This cannot be undone.', confirmText:'Delete'})) {
        const previousState = snapshotDayState();
        const deleteUrl = del.dataset.delete;
        let editUrl = null;
        mutateDayState(state => {
            editUrl = findBlockItem(state, deleteUrl, 'delete_url')?.block.edit_url || null;
            state.timeline = state.timeline.filter(item => item.kind !== 'block' || item.block.delete_url !== deleteUrl);
        });
        if (editUrl) { cancelBackgroundSync(`log-edit:${editUrl}`); cancelBackgroundSync(`event-edit:${editUrl}`); }
        closeOverlay(document.querySelector('[data-overlay="composer"]'));
        try {
            const body = await ajax(deleteUrl, {method:'DELETE'});
            toast(body.message);
        } catch(error) {
            restoreDayState(previousState);
            toast(error.message, true);
        }
    }
    const edit = e.target.closest('[data-edit-block]');
    if (edit) {
        const content = await modal({title:'Edit log entry', message:edit.dataset.updated ? `Updated ${edit.dataset.updated}` : '', initial:edit.dataset.content || '', confirmText:'Save'});
        if (content !== null) {
            const previousState = snapshotDayState();
            updateLocalTimelineBlock(edit.dataset.editBlock, {content});
            try { const body = await ajax(edit.dataset.editBlock,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({content})}); toast(body.message || 'Saved.'); }
            catch(error) { restoreDayState(previousState); toast(error.message,true); }
        }
    }
    const task = e.target.closest('[data-task-event]');
    if (task) {
        task.closest('[data-events-menu]')?.removeAttribute('open');
        let value = null; const options = JSON.parse(task.dataset.options || '[]');
        if (options.length) { value = await modal({title:task.dataset.name, message:'Choose a value before this event is tracked.', options, confirmText:'Track event'}); if (value === null) return; }
        const locationPromise = task.hasAttribute('data-capture-location') ? captureBrowserLocation() : Promise.resolve(null);
        const relatedButtons = [...document.querySelectorAll('[data-task-event]')].filter(button => button.dataset.taskEvent === task.dataset.taskEvent);
        const originalCounts = new Map();
        relatedButtons.forEach(button => {
            const count = button.querySelector('[data-count]');
            const buttonSlot = button.dataset.scheduledTime || '';
            const clickedSlot = task.dataset.scheduledTime || '';
            if (!count || (buttonSlot && buttonSlot !== clickedSlot)) return;
            originalCounts.set(count, count.textContent);
            count.textContent = String((Number.parseInt(count.textContent, 10) || 0) + 1);
        });
        const previousState = snapshotDayState();
        const eventUrl = task.dataset.taskEvent;
        const scheduledTime = task.dataset.scheduledTime || null;
        const taskName = task.dataset.name;
        const taskEmoji = task.querySelector('[aria-hidden="true"]')?.textContent?.trim() || '✅';
        const optimisticId = `local-event-${crypto.randomUUID?.() || Date.now()}`;
        const now = new Date();
        const optimisticTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        mutateDayState(state => {
            state.tasks.filter(item => item.event_url === eventUrl).forEach(item => { item.count += 1; });
            state.timeline.filter(item => item.kind === 'schedule' && item.task.event_url === eventUrl).forEach(item => {
                item.task.count += 1;
                if (!scheduledTime || item.time === scheduledTime) item.task.slot_count = Number(item.task.slot_count || 0) + 1;
            });
            addOptimisticTimelineBlock(state, {
            id:optimisticId,
            time:optimisticTime,
            emoji:taskEmoji,
            content:'',
            kind:'event',
            editUrl:eventUrl,
            eventName:taskName,
            selectedValue:value || '',
            });
        });
        task.disabled = true;
        const eventCreatePromise = ajax(eventUrl,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({value, scheduled_time:scheduledTime})});
        pendingEventCreates.set(optimisticId, eventCreatePromise);
        configureComposer({
            time:optimisticTime,
            mode:'edit',
            kind:'event',
            action:eventUrl,
            content:'',
            emoji:taskEmoji,
            pendingEventId:optimisticId,
        });
        let eventCreated = false;
        try {
            const body = await eventCreatePromise;
            eventCreated = true;
            reconcileOptimisticBlock(optimisticId, {...body, block:{id:body.block_id}});
            activeDayState.tasks.filter(item => item.event_url === eventUrl).forEach(item => { item.count = body.count; });
            toast(body.message);
            const composerRoot = document.querySelector('[data-overlay="composer"]');
            const composerForm = composerRoot?.querySelector('[data-composer-note-form]');
            if (composerForm?.dataset.pendingEventId === optimisticId) {
                composerForm.action = body.edit_url;
                delete composerForm.dataset.pendingEventId;
                const actions = composerRoot.querySelector('[data-composer-entry-actions]');
                actions?.classList.remove('hidden'); actions?.classList.add('grid');
                const visibility = composerRoot.querySelector('[data-composer-visibility]');
                if (visibility) { visibility.classList.remove('hidden'); visibility.textContent = 'Hide'; visibility.dataset.plannerVisibility = body.hide_url; visibility.dataset.method = 'PATCH'; visibility.dataset.payload = JSON.stringify({hidden:true}); }
                const deleteButton = composerRoot.querySelector('[data-composer-delete]');
                if (deleteButton) { deleteButton.classList.remove('hidden'); deleteButton.dataset.delete = body.delete_url; }
            }
            if (!backgroundSyncQueue.has(`pending-event-edit:${optimisticId}`)) pendingEventCreates.delete(optimisticId);
            const capturedLocation = await locationPromise;
            if (capturedLocation && body.location_url) {
                try {
                    const locationBody = await ajax(body.location_url, {method:'PATCH', headers:{'Content-Type':'application/json'}, body:JSON.stringify(capturedLocation)});
                    const item = activeDayState ? findPendingBlockItem(activeDayState, optimisticId) : null;
                    if (item?.block.event) item.block.event.location = locationBody.location;
                } catch (_) { toast('The event was logged, but its location could not be saved.', true); }
            }
        }
        catch(error) {
            if (!eventCreated) {
                cancelBackgroundSync(`pending-event-edit:${optimisticId}`);
                pendingEventCreates.delete(optimisticId);
                restoreDayState(previousState);
                const composer = document.querySelector('[data-overlay="composer"]');
                if (composer?.querySelector('[data-composer-note-form]')?.dataset.pendingEventId === optimisticId) closeOverlay(composer);
            }
            toast(error.message,true);
        } finally { task.disabled = false; }
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
            if (root.dataset.overlay === 'composer') saveComposerDraft(root);
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

const modelLoadOutcomes = new Map();
const modelLoadRequests = new Map();

function requestModelList(url) {
    if (modelLoadOutcomes.has(url)) return Promise.resolve(modelLoadOutcomes.get(url));
    if (modelLoadRequests.has(url)) return modelLoadRequests.get(url);

    const request = ajax(url)
        .then(body => ({models: body.data || [], error: null}))
        .catch(error => ({models: null, error}))
        .then(outcome => {
            modelLoadOutcomes.set(url, outcome);
            return outcome;
        })
        .finally(() => modelLoadRequests.delete(url));
    modelLoadRequests.set(url, request);

    return request;
}

async function loadModels(select) {
    if (select.dataset.modelsInitialized === 'true') return;
    select.dataset.modelsInitialized = 'true';
    const kind = select.dataset.modelSelect, modelKind = kind === 'chat-default' ? 'chat' : kind, key = `captainslog.models.${modelKind}`, choiceKey = `captainslog.model.${modelKind}`, accountChoice = select.dataset.selected || '';
    const render = models => {
        const choice = kind === 'chat-default' ? accountChoice : (localStorage.getItem(choiceKey) || accountChoice);
        const options = models.map(model => { const option = cloneTemplate('select-option-template'); option.textContent = model.name || model.id; option.value = model.id; return option; });
        if (!options.length) { const option = cloneTemplate('select-option-template'); option.textContent = 'No compatible models available'; option.value = ''; options.push(option); }
        select.replaceChildren(...options);
        if (choice && [...select.options].some(option => option.value === choice)) select.value = choice;
        select.dispatchEvent(new CustomEvent('modelsloaded', {bubbles: true}));
    };
    const cached = JSON.parse(localStorage.getItem(key) || '[]'); if (cached.length) render(cached);
    const url = `${select.dataset.modelsUrl}?images=${modelKind === 'image' ? 1 : 0}`;
    const outcome = await requestModelList(url);
    if (outcome.models) {
        localStorage.setItem(key, JSON.stringify(outcome.models));
        render(outcome.models);
    } else if (!cached.length) {
        const option = cloneTemplate('select-option-template'); option.textContent = accountChoice || 'Add an API key in Settings'; option.value = accountChoice; select.replaceChildren(option); select.dispatchEvent(new CustomEvent('modelsloaded', {bubbles: true}));
    }
    if (kind !== 'chat-default') select.addEventListener('change', () => localStorage.setItem(choiceKey, select.value));
}
document.querySelectorAll('[data-model-select]').forEach(loadModels);

const requestedPanel = new URLSearchParams(location.search).get('panel');
if (['chat', 'image'].includes(requestedPanel) && document.querySelector(`[data-overlay="${requestedPanel}"]`)) {
    openOverlay(requestedPanel);
}


function initializeRefreshedMain(root) {
    root.querySelectorAll('[data-time-picker]').forEach(initTimePicker);
    root.querySelectorAll('[data-emoji-picker]').forEach(initEmojiPicker);
    root.querySelectorAll('[data-model-select]').forEach(loadModels);
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
