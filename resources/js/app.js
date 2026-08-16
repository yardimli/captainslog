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

document.addEventListener('submit', async e => {
    const form = e.target.closest('form[data-ajax]'); if (!form) return;
    e.preventDefault(); const button = form.querySelector('[type=submit]'); if (button) button.disabled = true;
    try { const body = await ajax(form.action, {method: form.method || 'POST', body: new FormData(form)}); toast(body.message || 'Saved.'); if (body.reload) location.reload(); else form.reset(); }
    catch (error) { toast(error.message, true); } finally { if (button) button.disabled = false; }
});

document.addEventListener('click', async e => {
    if (e.target.closest('[data-theme-toggle]')) { document.documentElement.classList.toggle('dark'); localStorage.setItem('captainslog.theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light'); }
    const del = e.target.closest('[data-delete]');
    if (del && await modal({title:'Delete this item?', message:'This cannot be undone.', confirmText:'Delete'})) { try { const body = await ajax(del.dataset.delete, {method:'DELETE'}); toast(body.message); location.reload(); } catch(error) { toast(error.message, true); } }
    const edit = e.target.closest('[data-edit-block]');
    if (edit) { const content = await modal({title:'Edit log entry', initial:edit.dataset.content || '', confirmText:'Save'}); if (content !== null) try { await ajax(edit.dataset.editBlock,{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({content})}); location.reload(); } catch(error) { toast(error.message,true); } }
    const task = e.target.closest('[data-task-event]');
    if (task) {
        let value = null; const options = JSON.parse(task.dataset.options || '[]');
        if (options.length) { value = await modal({title:task.dataset.name, message:'Choose a value before this event is tracked.', options, confirmText:'Track event'}); if (value === null) return; }
        task.disabled = true;
        try {
            const body = await ajax(task.dataset.taskEvent,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({value})});
            document.querySelectorAll(`[data-task-event="${task.dataset.taskEvent}"] [data-count]`).forEach(count => { count.textContent = body.count; });
            toast(body.message);
            const addNotes = body.notes_url && await modal({title:'Event tracked', message:'Would you like to attach notes, a photo, or a recording?', confirmText:'Add notes & media', cancelText:'Done'});
            if (addNotes) location.href = body.notes_url;
            else location.reload();
        }
        catch(error) { toast(error.message,true); } finally { task.disabled = false; }
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
    const kind = select.dataset.modelSelect, key = `captainslog.models.${kind}`, choiceKey = `captainslog.model.${kind}`;
    const render = models => { const choice = localStorage.getItem(choiceKey); select.innerHTML = models.map(m => `<option value="${m.id}">${m.name || m.id}</option>`).join(''); if (choice && [...select.options].some(o => o.value === choice)) select.value = choice; };
    const cached = JSON.parse(localStorage.getItem(key) || '[]'); if (cached.length) render(cached);
    try { const body = await ajax(`${select.dataset.modelsUrl}?images=${kind === 'image' ? 1 : 0}`); const models = body.data || []; localStorage.setItem(key, JSON.stringify(models)); render(models); } catch(error) { if (!cached.length) select.innerHTML = '<option>Add an API key in Settings</option>'; }
    select.addEventListener('change', () => localStorage.setItem(choiceKey, select.value));
}
document.querySelectorAll('[data-model-select]').forEach(loadModels);

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
