import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TableKit } from '@tiptap/extension-table';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Highlight from '@tiptap/extension-highlight';
import { TextStyle } from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import TextAlign from '@tiptap/extension-text-align';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import Placeholder from '@tiptap/extension-placeholder';
import Mathematics from '@tiptap/extension-mathematics';
import { diffWords } from 'diff';
import 'katex/dist/katex.min.css';

const parseJsonElement = id => {
    try {
        return JSON.parse(document.getElementById(id)?.textContent || 'null');
    } catch (_) {
        return null;
    }
};

const escapeHtml = value => String(value || '').replace(/[&<>"']/g, character => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
})[character]);

function initialEditorContent(payload) {
    if (payload?.content_json?.type === 'doc') return payload.content_json;
    if (payload?.content_format === 'text') return `<p>${escapeHtml(payload.content).replace(/\r?\n/g, '<br>')}</p>`;
    return payload?.content || '<p></p>';
}

function initializeNoteEditor() {
    const element = document.querySelector('[data-note-rich-editor]');
    const form = element?.closest('form');
    if (!element || !form) return;
    const payload = parseJsonElement('note-editor-data');
    const config = parseJsonElement('note-config-data') || {};
    const contentField = form.querySelector('[name="content"]');
    const jsonField = form.querySelector('[data-note-content-json]');
    const plainTextField = form.querySelector('[data-note-plain-text]');
    const titleField = form.querySelector('[name="title"]');
    const wordCount = form.querySelector('[data-note-word-count]');
    const saveStatus = form.querySelector('[data-note-save-status]');
    let saveTimer = null;
    let savingPromise = null;
    let saveQueued = false;
    let autoTitleTimer = null;
    let autoTitleInFlight = false;
    let lastAutoTitleContent = null;

    const editor = new Editor({
        element,
        extensions: [
            StarterKit.configure({link: {openOnClick: false, autolink: true}}),
            TableKit.configure({table: {resizable: true}}),
            TaskList,
            TaskItem.configure({nested: true}),
            Highlight.configure({multicolor: true}),
            TextStyle,
            Color,
            TextAlign.configure({types: ['heading', 'paragraph']}),
            Subscript,
            Superscript,
            Placeholder.configure({placeholder: 'Start writing…'}),
            Mathematics.configure({katexOptions: {throwOnError: false}}),
        ],
        content: initialEditorContent(payload),
        editorProps: {attributes: {class: 'tiptap-note-content min-h-[20rem] focus:outline-none'}},
        onUpdate: ({editor: currentEditor}) => {
            syncFields(currentEditor);
            scheduleSave();
            scheduleAutoTitle();
        },
        onSelectionUpdate: ({editor: currentEditor}) => updateToolbar(currentEditor),
    });

    const syncFields = currentEditor => {
        const text = currentEditor.getText({blockSeparator: '\n'});
        contentField.value = currentEditor.getHTML();
        jsonField.value = JSON.stringify(currentEditor.getJSON());
        plainTextField.value = text;
        const words = text.trim() ? text.trim().split(/\s+/).length : 0;
        if (wordCount) wordCount.textContent = `${words} ${words === 1 ? 'word' : 'words'}`;
    };

    const setSaveStatus = (message, isError = false) => {
        if (!saveStatus) return;
        saveStatus.textContent = message;
        saveStatus.classList.toggle('text-rose-600', isError);
        saveStatus.classList.toggle('text-slate-500', !isError);
    };

    const draftHasContent = () => Boolean(titleField.value.trim() || plainTextField.value.trim());

    const responseError = async response => {
        const body = await response.json().catch(() => ({}));
        const validation = body.errors ? Object.values(body.errors).flat()[0] : null;
        return validation || body.message || 'The note could not be saved.';
    };

    const performSave = async () => {
        syncFields(editor);
        if (!config.note_id && !draftHasContent()) {
            setSaveStatus('Start typing to save');
            return null;
        }

        setSaveStatus('Saving…');
        const data = new FormData(form);
        if (config.note_id) data.set('_method', 'PATCH');
        const response = await fetch(config.update_url || config.store_url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: data,
        });
        if (!response.ok) throw new Error(await responseError(response));
        const body = await response.json();
        if (!config.note_id) {
            Object.assign(config, body);
            window.history.replaceState({}, '', body.show_url);
        }
        setSaveStatus('Saved');
        return body;
    };

    const saveNote = async () => {
        if (savingPromise) {
            saveQueued = true;
            return savingPromise;
        }
        savingPromise = performSave();
        try {
            return await savingPromise;
        } catch (error) {
            setSaveStatus(error.message, true);
            return null;
        } finally {
            savingPromise = null;
            if (saveQueued) {
                saveQueued = false;
                window.setTimeout(saveNote, 0);
            }
        }
    };

    function scheduleSave(delay = 800) {
        window.clearTimeout(saveTimer);
        if (!config.note_id && !draftHasContent()) return;
        setSaveStatus('Unsaved changes');
        saveTimer = window.setTimeout(saveNote, delay);
    }

    const ensureSaved = async () => {
        window.clearTimeout(saveTimer);
        if (!config.note_id && !draftHasContent()) return false;
        if (savingPromise) await savingPromise;
        await saveNote();
        if (savingPromise) await savingPromise;
        return Boolean(config.note_id);
    };

    const isUntitled = () => !titleField.value.trim() || titleField.value.trim().toLowerCase() === 'untitled';

    const selectedAiModel = () => document.querySelector('#note-ai-model')?.value
        || window.localStorage.getItem('captainslog.model.chat')
        || config.default_model
        || '';

    const requestAi = async (prompt, mode, model = selectedAiModel()) => {
        if (!model) throw new Error('Choose an AI model before generating a title.');
        const response = await fetch(config.ai_url, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body: JSON.stringify({prompt, model, mode}),
        });
        if (!response.ok) throw new Error(await responseError(response));
        return response.json();
    };

    async function generateAutomaticTitle() {
        syncFields(editor);
        const currentContent = plainTextField.value.trim();
        if (autoTitleInFlight || !isUntitled() || currentContent.length < 10 || currentContent === lastAutoTitleContent) return;
        const model = selectedAiModel();
        if (!model) {
            setSaveStatus('Saved · choose an AI model to generate a title', true);
            return;
        }

        autoTitleInFlight = true;
        try {
            if (!await ensureSaved() || !isUntitled()) return;
            const body = await requestAi('Create a concise, specific title that captures the main subject of this completed note.', 'title', model);
            syncFields(editor);
            if (!isUntitled() || plainTextField.value.trim() !== currentContent) return;
            const generatedTitle = body.text.replace(/^['\"]|['\"]$/g, '').trim().slice(0, 500);
            if (!generatedTitle) throw new Error('The selected model returned an empty title.');
            const previousTitle = titleField.value;
            titleField.value = generatedTitle;
            const saved = await saveNote();
            if (saved) {
                lastAutoTitleContent = currentContent;
            } else {
                titleField.value = previousTitle;
            }
        } catch (error) {
            console.warn('Automatic note title generation failed:', error.message);
            setSaveStatus('Saved · automatic title could not be generated', true);
        } finally {
            autoTitleInFlight = false;
        }
    }

    function scheduleAutoTitle() {
        window.clearTimeout(autoTitleTimer);
        if (!isUntitled() || plainTextField.value.trim().length < 10) return;
        autoTitleTimer = window.setTimeout(generateAutomaticTitle, config.auto_title_delay_ms || 8000);
    }

    const commandIsActive = (currentEditor, command) => {
        const mapping = {
            bold: ['bold'], italic: ['italic'], underline: ['underline'], strike: ['strike'], code: ['code'],
            heading1: ['heading', {level: 1}], heading2: ['heading', {level: 2}], heading3: ['heading', {level: 3}],
            bulletList: ['bulletList'], orderedList: ['orderedList'], taskList: ['taskList'], blockquote: ['blockquote'],
            codeBlock: ['codeBlock'], subscript: ['subscript'], superscript: ['superscript'], link: ['link'],
        };
        return mapping[command] ? currentEditor.isActive(...mapping[command]) : false;
    };

    const updateToolbar = currentEditor => {
        form.querySelectorAll('[data-note-command]').forEach(button => {
            button.classList.toggle('is-active', commandIsActive(currentEditor, button.dataset.noteCommand));
        });
    };

    const runCommand = command => {
        const chain = editor.chain().focus();
        const commands = {
            undo: () => chain.undo().run(), redo: () => chain.redo().run(), bold: () => chain.toggleBold().run(),
            italic: () => chain.toggleItalic().run(), underline: () => chain.toggleUnderline().run(), strike: () => chain.toggleStrike().run(),
            code: () => chain.toggleCode().run(), heading1: () => chain.toggleHeading({level: 1}).run(),
            heading2: () => chain.toggleHeading({level: 2}).run(), heading3: () => chain.toggleHeading({level: 3}).run(),
            bulletList: () => chain.toggleBulletList().run(), orderedList: () => chain.toggleOrderedList().run(),
            taskList: () => chain.toggleTaskList().run(), blockquote: () => chain.toggleBlockquote().run(),
            codeBlock: () => chain.toggleCodeBlock().run(), horizontalRule: () => chain.setHorizontalRule().run(),
            alignLeft: () => chain.setTextAlign('left').run(), alignCenter: () => chain.setTextAlign('center').run(),
            alignRight: () => chain.setTextAlign('right').run(), subscript: () => chain.toggleSubscript().run(),
            superscript: () => chain.toggleSuperscript().run(), table: () => chain.insertTable({rows: 3, cols: 3, withHeaderRow: true}).run(),
            link: () => {
                const href = window.prompt('Link URL', editor.getAttributes('link').href || 'https://');
                if (href === null) return false;
                return href.trim() ? chain.extendMarkRange('link').setLink({href: href.trim()}).run() : chain.unsetLink().run();
            },
            math: () => {
                const latex = window.prompt('LaTeX formula', 'x^2 + y^2 = z^2');
                return latex?.trim() ? chain.insertInlineMath({latex: latex.trim()}).run() : false;
            },
        };
        commands[command]?.();
        updateToolbar(editor);
        syncFields(editor);
        scheduleSave();
    };

    form.querySelectorAll('[data-note-command]').forEach(button => button.addEventListener('click', () => runCommand(button.dataset.noteCommand)));
    form.querySelector('[data-note-text-color]')?.addEventListener('input', event => editor.chain().focus().setColor(event.target.value).run());
    form.querySelector('[data-note-highlight-color]')?.addEventListener('input', event => editor.chain().focus().setHighlight({color: event.target.value}).run());
    titleField?.addEventListener('input', () => {
        scheduleSave();
        scheduleAutoTitle();
    });
    form.querySelector('[name="notebook_id"]')?.addEventListener('change', () => scheduleSave(100));
    form.querySelector('[name="color"]')?.addEventListener('input', () => scheduleSave());
    form.querySelectorAll('[name="tag_ids[]"]').forEach(input => input.addEventListener('change', () => scheduleSave(100)));
    form.addEventListener('submit', event => { event.preventDefault(); saveNote(); });

    const aiDialog = document.querySelector('[data-note-ai-dialog]');
    const aiForm = aiDialog?.querySelector('[data-note-ai-form]');
    const aiError = aiDialog?.querySelector('[data-note-ai-error]');
    const aiSubmit = aiDialog?.querySelector('[data-note-ai-submit]');
    const aiAction = aiForm?.querySelector('[name="mode"]');
    aiForm?.querySelector('[name="model"]')?.addEventListener('change', scheduleAutoTitle);
    aiForm?.querySelector('[name="model"]')?.addEventListener('modelsloaded', scheduleAutoTitle);
    const updateAiButtonLabel = () => {
        const label = aiSubmit?.querySelector('[data-button-label]');
        if (label) label.textContent = aiAction?.value === 'title' ? 'Generate title' : 'Generate and append';
    };
    aiAction?.addEventListener('change', updateAiButtonLabel);
    document.querySelector('[data-note-ai-open]')?.addEventListener('click', async () => {
        if (!await ensureSaved()) {
            setSaveStatus('Type a title or some note text before using AI', true);
            return;
        }
        aiError?.classList.add('hidden');
        aiDialog?.showModal();
        aiForm?.querySelector('[name="prompt"]')?.focus();
    });
    aiDialog?.querySelector('[data-dialog-close]')?.addEventListener('click', () => aiDialog.close());
    aiDialog?.addEventListener('click', event => { if (event.target === aiDialog) aiDialog.close(); });
    aiForm?.addEventListener('submit', async event => {
        event.preventDefault();
        aiError?.classList.add('hidden');
        aiSubmit.disabled = true;
        aiSubmit.querySelector('[data-button-label]').textContent = 'Writing…';
        try {
            if (!await ensureSaved()) throw new Error('Save the note before using AI.');
            const body = await requestAi(aiForm.prompt.value, aiForm.mode.value, aiForm.model.value);
            if (body.mode === 'title') {
                titleField.value = body.text.replace(/^['\"]|['\"]$/g, '').slice(0, 500);
            } else {
                const generatedHtml = body.text.split(/\n{2,}/).map(paragraph => `<p>${escapeHtml(paragraph).replace(/\n/g, '<br>')}</p>`).join('');
                editor.chain().focus('end').insertContent(generatedHtml).run();
            }
            syncFields(editor);
            await saveNote();
            aiForm.querySelector('[name="prompt"]').value = '';
            aiDialog.close();
        } catch (error) {
            aiError.textContent = error.message;
            aiError.classList.remove('hidden');
        } finally {
            aiSubmit.disabled = false;
            updateAiButtonLabel();
        }
    });
    updateAiButtonLabel();
    syncFields(editor);
    updateToolbar(editor);
    scheduleAutoTitle();
}

function initializeNotebookDialog() {
    const dialog = document.querySelector('[data-notebook-dialog]');
    if (!dialog) return;
    document.querySelectorAll('[data-notebook-dialog-open]').forEach(button => button.addEventListener('click', () => dialog.showModal()));
    dialog.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
}

function initializeNoteColor() {
    const input = document.querySelector('#note-color');
    const editor = document.querySelector('#note-editor');
    input?.addEventListener('input', () => { editor.style.borderTopColor = input.value; });
}

function initializeVersionDialog() {
    const dialog = document.querySelector('[data-version-dialog]');
    const versions = parseJsonElement('note-version-data');
    if (!dialog || !Array.isArray(versions) || versions.length < 2) return;
    const fromSelect = dialog.querySelector('[data-version-from]');
    const toSelect = dialog.querySelector('[data-version-to]');
    const output = dialog.querySelector('[data-version-diff-output]');
    const restoreChoice = dialog.querySelector('[data-version-restore-choice]');
    const restoreForm = document.querySelector('[data-version-restore-form]');

    const optionFor = version => {
        const option = document.createElement('option');
        option.value = version.id;
        option.textContent = `Version ${version.number} · ${version.created_label || 'Unknown time'} · ${version.source}`;
        return option;
    };
    versions.forEach(version => { fromSelect.append(optionFor(version)); toSelect.append(optionFor(version)); });
    fromSelect.value = versions.at(-2).id;
    toSelect.value = versions.at(-1).id;

    const selectedVersion = select => versions.find(version => String(version.id) === select.value);
    const renderDiff = () => {
        const from = selectedVersion(fromSelect), to = selectedVersion(toSelect);
        output.replaceChildren();
        if (!from || !to) return;
        diffWords(`Title: ${from.title || 'Untitled'}\n\n${from.plain_text}`, `Title: ${to.title || 'Untitled'}\n\n${to.plain_text}`).forEach(part => {
            const span = document.createElement('span');
            span.textContent = part.value;
            if (part.added) span.className = 'rounded bg-emerald-200 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-100';
            if (part.removed) span.className = 'rounded bg-rose-200 text-rose-950 line-through dark:bg-rose-900 dark:text-rose-100';
            output.append(span);
        });
        restoreChoice.classList.add('hidden');
    };

    document.querySelector('[data-version-dialog-open]')?.addEventListener('click', () => { renderDiff(); dialog.showModal(); });
    dialog.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    fromSelect.addEventListener('change', renderDiff);
    toSelect.addEventListener('change', renderDiff);
    dialog.querySelector('[data-version-restore-open]')?.addEventListener('click', () => restoreChoice.classList.toggle('hidden'));
    dialog.querySelectorAll('[data-version-restore-mode]').forEach(button => button.addEventListener('click', () => {
        const version = selectedVersion(fromSelect);
        const mode = button.dataset.versionRestoreMode;
        if (!version || !restoreForm) return;
        if (mode === 'undo' && !window.confirm(`Undo to version ${version.number}? Every newer version will be permanently removed.`)) return;
        restoreForm.action = version.restore_url;
        restoreForm.querySelector('[data-version-restore-mode-field]').value = mode;
        restoreForm.submit();
    }));
    renderDiff();
}

initializeNoteEditor();
initializeNotebookDialog();
initializeNoteColor();
initializeVersionDialog();
