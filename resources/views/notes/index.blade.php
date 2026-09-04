@extends('layouts.app')

@section('content')
    @php
        $editing = ! $creating && isset($note) && $note;
        $selectedNotebook = old('notebook_id', $editing ? $note->notebook_id : ($activeNotebook?->id ?? optional(auth()->user()->noteSettings)->default_notebook_id));
        $noteColor = old('color', $editing ? ($note->color ?: '#6366f1') : '#6366f1');
        $editorPayload = [
            'content' => $editing ? $note->content : '',
            'content_json' => $editing ? $note->content_json : null,
            'content_format' => $editing ? $note->content_format : 'tiptap',
        ];
        $noteConfig = [
            'note_id' => $editing ? $note->id : null,
            'store_url' => route('notes.store'),
            'update_url' => $editing ? route('notes.update', $note) : null,
            'show_url' => $editing ? route('notes.show', $note) : null,
            'ai_url' => $editing ? route('notes.ai', $note) : null,
            'default_model' => auth()->user()->default_chat_model,
            'auto_title_delay_ms' => 8000,
        ];
        $versionPayload = $editing ? $note->versions->sortBy('version_number')->values()->map(fn ($version) => [
            'id' => $version->id,
            'number' => $version->version_number,
            'title' => $version->title,
            'plain_text' => $version->plain_text ?? '',
            'content' => $version->content ?? '',
            'created_at' => $version->created_at?->toIso8601String(),
            'created_label' => $version->created_at?->format('M j, Y · H:i'),
            'source' => $version->change_source,
            'restore_url' => route('notes.versions.restore', [$note, $version]),
        ])->all() : [];
    @endphp

    <div id="notes-workspace" class="grid h-[calc(100dvh-4rem)] w-full min-h-0 overflow-y-auto bg-slate-100 dark:bg-slate-950 {{ $currentView === 'notes' ? 'lg:grid-cols-[14rem_21rem_minmax(0,1fr)]' : 'lg:grid-cols-[14rem_minmax(0,1fr)]' }} lg:overflow-hidden">
        <aside id="notes-navigation" class="min-h-0 overflow-y-auto border-r border-slate-200 bg-white p-2 dark:border-slate-800 dark:bg-slate-900 lg:h-full">
            <a class="btn mb-2 w-full rounded-md px-3 py-2" href="{{ route('notes.create', $activeNotebook ? ['notebook' => $activeNotebook->id] : []) }}"><svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>New note</a>
            <nav id="notes-primary-links" class="space-y-0.5">
                @foreach([['notes','▤','All notes',$allNotesCount],['tasks','✓','Tasks',$noteTasks->where('is_completed', false)->count()],['tags','#','Tags',$tags->count()],['trash','♲','Trash',$trashCount]] as [$viewKey,$icon,$label,$count])
                    <a href="{{ $viewKey === 'notes' ? route('notes.index') : route('notes.index', ['view' => $viewKey]) }}" class="flex items-center gap-2 rounded-md px-2.5 py-2 text-sm {{ $currentView === $viewKey && ! $activeNotebook && ! $activeTag ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}"><span class="w-5 text-center text-slate-400">{{ $icon }}</span><span class="flex-1">{{ $label }}</span><span class="text-xs text-slate-400">{{ $count }}</span></a>
                @endforeach
            </nav>
            <div id="notes-notebook-list" class="mt-4"><div id="notes-notebook-heading" class="flex items-center px-2.5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Notebooks</p><button type="button" class="ml-auto text-lg leading-none text-indigo-600" data-notebook-dialog-open title="Create notebook">+</button></div><ul class="mt-1 space-y-0.5">@foreach($notebooks as $notebook)<li><a href="{{ route('notes.index', ['notebook' => $notebook->id]) }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm {{ $activeNotebook?->is($notebook) ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}" @if($activeNotebook?->is($notebook)) aria-current="page" @endif><span class="h-2 w-2 shrink-0 rounded-sm" style="background-color: {{ $notebook->color ?: '#6366f1' }}"></span><span class="min-w-0 flex-1 truncate">{{ $notebook->name }}</span><span class="text-xs text-slate-400">{{ $notebook->notes_count }}</span></a></li>@endforeach</ul></div>
            <div id="notes-tag-list" class="mt-4"><div id="notes-tag-heading" class="flex items-center px-2.5"><p class="text-xs font-bold uppercase tracking-wider text-slate-500">Tags</p><a href="{{ route('notes.index', ['view' => 'tags']) }}" class="ml-auto text-xs text-indigo-600">Manage</a></div><ul class="mt-1 space-y-0.5">@foreach($tags->take(8) as $tag)<li><a href="{{ route('notes.index', ['tag' => $tag->id]) }}" class="flex items-center gap-2 rounded-md px-2.5 py-1.5 text-sm {{ $activeTag?->is($tag) ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-200' : 'hover:bg-slate-100 dark:hover:bg-slate-800' }}"><span class="h-2 w-2 rounded-full" style="background-color: {{ $tag->color ?: '#64748b' }}"></span><span class="min-w-0 flex-1 truncate">{{ $tag->name }}</span><span class="text-xs text-slate-400">{{ $tag->notes_count }}</span></a></li>@endforeach</ul></div>
        </aside>

        @if($currentView === 'notes')
        <section id="notes-list" class="min-h-0 overflow-y-auto border-r border-slate-200 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-950 lg:h-full">
            <div id="notes-list-heading" class="flex items-center justify-between px-1 py-1.5"><div id="notes-list-heading-copy"><h2 class="font-bold">{{ $activeNotebook?->name ?? $activeTag?->name ?? 'Notes' }}</h2><p class="text-xs text-slate-500">{{ $notes->count() }} {{ Str::plural('note', $notes->count()) }}</p></div><span class="text-xs text-slate-500">Updated</span></div>
            <div id="notes-list-items" class="space-y-2">
                @forelse($notes as $listedNote)
                    <a href="{{ route('notes.show', ['note' => $listedNote, 'notebook' => $activeNotebook?->id, 'tag' => $activeTag?->id]) }}" style="border-left-color: {{ $listedNote->color ?: '#6366f1' }}" class="block rounded-md border border-l-4 bg-white px-3 py-2.5 shadow-sm transition hover:border-indigo-300 dark:bg-slate-900 dark:hover:border-indigo-700 {{ isset($note) && $note?->is($listedNote) ? 'ring-1 ring-indigo-400' : '' }}">
                        <strong class="block truncate text-sm">{{ $listedNote->title ?: 'Untitled note' }}</strong>
                        <span class="mt-1 block line-clamp-2 text-xs leading-5 text-slate-500">{{ $listedNote->excerpt ?: 'Empty note' }}</span>
                        @if($listedNote->tags->isNotEmpty())<span class="mt-2 flex flex-wrap gap-1">@foreach($listedNote->tags->take(3) as $tag)<span class="rounded-sm bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">#{{ $tag->name }}</span>@endforeach</span>@endif
                        <span class="mt-2 flex items-center gap-1 text-[11px] text-slate-400"><span>{{ $listedNote->notebook->name }}</span><span>·</span><span>{{ $listedNote->updated_at->diffForHumans() }}</span>@if($listedNote->tasks_count)<span class="ml-auto">✓ {{ $listedNote->tasks_count }}</span>@endif @if($listedNote->versions_count > 1)<span title="Has previous versions">↶ {{ $listedNote->versions_count }}</span>@endif</span>
                    </a>
                @empty
                    <div id="notes-empty-list" class="rounded-md border border-dashed border-slate-300 bg-white px-4 py-10 text-center dark:border-slate-700 dark:bg-slate-900"><span class="text-3xl" aria-hidden="true">🗒️</span><p class="mt-3 font-semibold">No notes here</p><p class="mt-1 text-sm text-slate-500">Create a note in this view.</p></div>
                @endforelse
            </div>
        </section>

        <section id="note-editor" class="relative flex min-h-[34rem] flex-col overflow-hidden border-t-4 bg-white dark:bg-slate-900 lg:h-full lg:min-h-0" style="border-top-color: {{ $noteColor }}">
            <form id="note-editor-form" method="POST" action="{{ $editing ? route('notes.update', $note) : route('notes.store') }}" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                @csrf
                @if($editing) @method('PATCH') @endif
                <div id="note-metadata-toolbar" class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2 dark:border-slate-800">
                    <label class="sr-only" for="note-notebook">Notebook</label>
                    <select id="note-notebook" name="notebook_id" class="input max-w-52 text-sm" required>@foreach($notebooks as $notebook)<option value="{{ $notebook->id }}" @selected((int) $selectedNotebook === $notebook->id)>{{ $notebook->name }}</option>@endforeach</select>
                    <label class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500" for="note-color">Color <input id="note-color" type="color" name="color" value="{{ $noteColor }}" class="h-7 w-8 cursor-pointer rounded-sm border border-slate-300 bg-transparent p-0.5 dark:border-slate-700"></label>
                    <details id="note-tag-picker" class="relative"><summary class="cursor-pointer rounded-md border border-slate-300 px-2 py-1.5 text-xs font-semibold dark:border-slate-700"># Tags</summary><div id="note-tag-picker-menu" class="absolute left-0 top-9 z-30 max-h-56 w-52 overflow-y-auto rounded-md border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">@forelse($tags as $tag)<label class="flex items-center gap-2 px-1 py-1 text-xs"><input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" @checked($editing && $note->tags->contains($tag))><span class="h-2 w-2 rounded-full" style="background-color: {{ $tag->color ?: '#64748b' }}"></span>{{ $tag->name }}</label>@empty<p class="text-xs text-slate-500">Create tags from the Tags view.</p>@endforelse</div></details>
                    @if($editing && $note->versions_count > 1)<button type="button" class="btn-secondary" data-version-dialog-open title="View previous versions">↶ {{ $note->versions_count - 1 }} previous</button>@elseif($editing)<span class="text-xs text-slate-400">No previous versions</span>@endif
                    <span class="ml-auto text-xs text-slate-500" data-note-save-status>{{ $editing ? 'Saved' : 'Start typing to save' }}</span>
                </div>
                <label class="sr-only" for="note-title">Note title</label>
                <input id="note-title" name="title" value="{{ old('title', $editing ? $note->title : '') }}" class="mx-5 mt-3 border-0 bg-transparent px-0 text-3xl font-black shadow-none placeholder:text-slate-300 focus:ring-0 dark:placeholder:text-slate-700" maxlength="500" placeholder="Untitled note" autofocus>

                <div id="note-rich-toolbar" class="mx-3 mt-2 flex shrink-0 flex-wrap gap-1 rounded-md border border-slate-200 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-950" role="toolbar" aria-label="Note formatting">
                    @foreach([['undo','↶','Undo'],['redo','↷','Redo'],['bold','B','Bold'],['italic','I','Italic'],['underline','U','Underline'],['strike','S','Strike'],['code','<>','Inline code'],['heading1','H1','Heading 1'],['heading2','H2','Heading 2'],['heading3','H3','Heading 3'],['bulletList','• List','Bullet list'],['orderedList','1. List','Numbered list'],['taskList','☑','Checklist'],['blockquote','❝','Quote'],['codeBlock','{ }','Code block'],['horizontalRule','—','Divider'],['alignLeft','⇤','Align left'],['alignCenter','↔','Align center'],['alignRight','⇥','Align right'],['subscript','X₂','Subscript'],['superscript','X²','Superscript'],['link','🔗','Link'],['table','▦','Insert table'],['math','∑','LaTeX formula']] as [$command,$label,$title])<button type="button" class="note-toolbar-button rounded-lg border border-transparent px-2 py-1.5 text-xs font-bold hover:border-slate-300 hover:bg-white dark:hover:border-slate-600 dark:hover:bg-slate-900" data-note-command="{{ $command }}" title="{{ $title }}" aria-label="{{ $title }}">{{ $label }}</button>@endforeach
                    <button type="button" class="note-toolbar-button rounded-lg border border-indigo-200 px-2 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-950" data-note-ai-open title="Write with AI" aria-label="Write with AI">✦ AI</button>
                    <label class="note-toolbar-button flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold" title="Text color">A<input type="color" value="#4f46e5" class="h-6 w-6 cursor-pointer border-0 bg-transparent p-0" data-note-text-color aria-label="Text color"></label>
                    <label class="note-toolbar-button flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold" title="Highlight color">▰<input type="color" value="#fde68a" class="h-6 w-6 cursor-pointer border-0 bg-transparent p-0" data-note-highlight-color aria-label="Highlight color"></label>
                </div>

                <div id="note-rich-editor-scroll" class="min-h-0 flex-1 overflow-y-auto px-5 py-3">
                    <div id="note-rich-editor" data-note-rich-editor class="mx-auto min-h-[20rem] max-w-5xl"></div>
                </div>
                <textarea id="note-content" name="content" class="hidden">{{ old('content', $editing ? $note->content : '') }}</textarea>
                <input type="hidden" name="content_json" data-note-content-json>
                <input type="hidden" name="plain_text" data-note-plain-text>
                <script type="application/json" id="note-editor-data">@json($editorPayload)</script>
                <script type="application/json" id="note-config-data">@json($noteConfig)</script>
                @if($errors->any())<div id="note-validation-errors" class="mx-4 mb-3 rounded-md bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-950 dark:text-rose-200">{{ $errors->first() }}</div>@endif
                <div id="note-editor-actions" class="flex shrink-0 items-center gap-2 border-t border-slate-200 px-3 py-2 dark:border-slate-800"><span class="text-xs text-slate-500" data-note-word-count>0 words</span><span class="ml-auto text-xs text-slate-400">Changes save automatically</span></div>
            </form>
            @if($editing)<form id="note-delete-form" method="POST" action="{{ route('notes.destroy', $note) }}" class="absolute bottom-2 left-2" data-confirm-delete data-confirm-title="Move this note to Trash?" data-confirm-message="The note will leave your notebooks, but you can restore it from Trash." data-confirm-text="Move to Trash">@csrf @method('DELETE')<button class="rounded-md px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950">Move to Trash</button></form>@endif
        </section>
        @else
        <main id="notes-utility-view" class="min-h-0 overflow-y-auto p-3 lg:h-full">
            @if($currentView === 'tasks')
                <section id="notes-tasks-view" class="mx-auto max-w-5xl"><header class="mb-3 flex items-end justify-between"><div id="notes-tasks-heading"><h1 class="text-2xl font-black">Tasks</h1><p class="text-sm text-slate-500">Lightweight actions from your notes—no reminders or calendar scheduling.</p></div><span class="text-sm text-slate-500">{{ $noteTasks->where('is_completed', false)->count() }} open</span></header>
                    <form method="POST" action="{{ route('note-tasks.store') }}" class="mb-3 grid gap-2 rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-[minmax(0,1fr)_15rem_auto]">@csrf<input class="input rounded-md" name="title" placeholder="Add a task" required maxlength="500"><select class="input rounded-md" name="note_id"><option value="">No linked note</option>@foreach(auth()->user()->notes()->latest()->limit(100)->get() as $taskNote)<option value="{{ $taskNote->id }}">{{ $taskNote->title }}</option>@endforeach</select><button class="btn rounded-md">Add task</button></form>
                    <div id="notes-task-cards" class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">@forelse($noteTasks as $task)<article class="rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="block-note-task-row flex items-start gap-2" id="note-task-row-{{ $task->id }}"><form method="POST" action="{{ route('note-tasks.update', $task) }}">@csrf @method('PATCH')<input type="hidden" name="is_completed" value="{{ $task->is_completed ? 0 : 1 }}"><button class="mt-0.5 h-5 w-5 rounded-full border-2 {{ $task->is_completed ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-indigo-400' }}" title="Toggle task">@if($task->is_completed)✓@endif</button></form><div class="block-note-task-copy min-w-0 flex-1" id="note-task-copy-{{ $task->id }}"><p class="text-sm font-semibold {{ $task->is_completed ? 'line-through text-slate-400' : '' }}">{{ $task->title }}</p>@if($task->note)<a class="mt-1 block truncate text-xs text-indigo-600" href="{{ route('notes.show', $task->note) }}">{{ $task->note->title }}</a>@endif</div><form method="POST" action="{{ route('note-tasks.destroy', $task) }}" data-confirm-delete data-confirm-title="Delete this task?" data-confirm-message="This task will be permanently deleted." data-confirm-text="Delete task">@csrf @method('DELETE')<button class="text-xs text-rose-600" title="Delete task">×</button></form></div></article>@empty<div id="notes-tasks-empty" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">No tasks yet.</div>@endforelse</div>
                </section>
            @elseif($currentView === 'tags')
                <section id="notes-tags-view" class="mx-auto max-w-5xl"><header class="mb-3"><h1 class="text-2xl font-black">Tags</h1><p class="text-sm text-slate-500">Create tags, filter notes, and assign them from the editor toolbar.</p></header><form method="POST" action="{{ route('note-tags.store') }}" class="mb-3 grid gap-2 rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-[minmax(0,1fr)_5rem_auto]">@csrf<input class="input rounded-md" name="name" placeholder="Tag name" required maxlength="120"><input class="h-10 w-full rounded-md border border-slate-300 p-1" type="color" name="color" value="#6366f1"><button class="btn rounded-md">Create tag</button></form><div id="notes-tag-cards" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@forelse($tags as $tag)<article class="rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="block-note-tag-row flex items-center gap-2" id="note-tag-card-row-{{ $tag->id }}"><span class="h-3 w-3 rounded-full" style="background-color: {{ $tag->color ?: '#64748b' }}"></span><a class="min-w-0 flex-1 truncate font-semibold" href="{{ route('notes.index', ['tag' => $tag->id]) }}">{{ $tag->name }}</a><span class="text-xs text-slate-400">{{ $tag->notes_count }}</span><form method="POST" action="{{ route('note-tags.destroy', $tag) }}" data-confirm-delete data-confirm-title="Delete this tag?" data-confirm-message="The tag will be removed from every note. The notes themselves will stay unchanged." data-confirm-text="Delete tag">@csrf @method('DELETE')<button class="text-rose-600" title="Delete tag">×</button></form></div></article>@empty<div id="notes-tags-empty" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">No tags yet.</div>@endforelse</div></section>
            @else
                <section id="notes-trash-view" class="mx-auto max-w-5xl"><header class="mb-3"><h1 class="text-2xl font-black">Trash</h1><p class="text-sm text-slate-500">Restore notes or permanently delete them.</p></header><div id="notes-trash-cards" class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">@forelse($notes as $trashedNote)<article class="rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="truncate font-bold">{{ $trashedNote->title ?: 'Untitled note' }}</h2><p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $trashedNote->excerpt ?: 'Empty note' }}</p><p class="mt-2 text-[11px] text-slate-400">Deleted {{ $trashedNote->deleted_at->diffForHumans() }}</p><div class="block-trashed-actions mt-3 flex gap-2" id="trashed-note-actions-{{ $trashedNote->id }}"><form method="POST" action="{{ route('notes.restore', $trashedNote->id) }}">@csrf<button class="btn rounded-md px-3 py-1.5 text-xs">Restore</button></form><form method="POST" action="{{ route('notes.force-destroy', $trashedNote->id) }}" data-confirm-delete data-confirm-title="Permanently delete this note?" data-confirm-message="This note and its version history will be permanently deleted. This cannot be undone." data-confirm-text="Delete forever">@csrf @method('DELETE')<button class="btn-secondary rounded-md px-3 py-1.5 text-xs text-rose-600">Delete forever</button></form></div></article>@empty<div id="notes-trash-empty" class="rounded-md border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">Trash is empty.</div>@endforelse</div></section>
            @endif
        </main>
        @endif
    </div>

    <dialog id="notebook-dialog" class="w-[min(28rem,calc(100vw-2rem))] rounded-md bg-transparent p-0 backdrop:bg-slate-950/60" data-notebook-dialog>
        <form method="POST" action="{{ route('notebooks.store') }}" class="panel space-y-4 !rounded-md">@csrf<div id="notebook-dialog-heading" class="flex items-center gap-3"><div id="notebook-dialog-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Organization</p><h2 class="text-xl font-black">Create notebook</h2></div><button type="button" class="btn-secondary ml-auto" data-dialog-close>Close</button></div><div id="notebook-name-field"><label class="label" for="notebook-name">Name</label><input id="notebook-name" class="input" name="name" maxlength="160" required></div><div id="notebook-color-field"><label class="label" for="notebook-color">Color</label><input id="notebook-color" type="color" name="color" value="#6366f1" class="h-12 w-full cursor-pointer rounded-md border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-950"></div><button class="btn w-full rounded-md">Create notebook</button></form>
    </dialog>

    <dialog id="note-ai-dialog" class="w-[min(36rem,calc(100vw-2rem))] rounded-md bg-transparent p-0 backdrop:bg-slate-950/60" data-note-ai-dialog>
        <form class="panel space-y-4 !rounded-md" data-note-ai-form>
            <div id="note-ai-dialog-heading" class="flex items-center gap-3"><div id="note-ai-dialog-copy"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Writing assistant</p><h2 class="text-xl font-black">Write with AI</h2></div><button type="button" class="btn-secondary ml-auto" data-dialog-close>Close</button></div>
            <p class="text-sm text-slate-500">The current note is sent as context. Append the response or use it to replace an Untitled title.</p>
            <div id="note-ai-model-field"><label class="label" for="note-ai-model">Model</label><select id="note-ai-model" class="input" name="model" data-model-select="chat" data-models-url="{{ route('openrouter.models') }}" data-selected="{{ auth()->user()->default_chat_model }}" required><option value="">Loading models…</option></select></div>
            <div id="note-ai-action-field"><label class="label" for="note-ai-action">Use response</label><select id="note-ai-action" class="input" name="mode"><option value="append">Append to note</option><option value="title">Use as note title</option></select></div>
            <div id="note-ai-command-field"><label class="label" for="note-ai-command">Command</label><textarea id="note-ai-command" class="input" name="prompt" rows="5" maxlength="10000" placeholder="Continue this idea, summarize the note, draft the next section…" required></textarea></div>
            <p class="hidden rounded-md bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950 dark:text-rose-200" data-note-ai-error></p>
            <button type="submit" class="btn w-full" data-note-ai-submit><span data-button-label>Generate and append</span></button>
        </form>
    </dialog>

    @if($editing)
        <dialog id="version-dialog" class="h-[min(48rem,calc(100dvh-2rem))] w-[min(64rem,calc(100vw-2rem))] rounded-md bg-transparent p-0 backdrop:bg-slate-950/60" data-version-dialog>
            <section class="panel flex h-full min-h-0 flex-col overflow-hidden p-0 !rounded-md">
                <header class="flex shrink-0 items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-800"><div id="version-dialog-heading"><p class="text-xs font-bold uppercase tracking-wider text-indigo-600">History</p><h2 class="text-xl font-black">Compare note versions</h2></div><button type="button" class="btn-secondary ml-auto" data-dialog-close>Close</button></header>
                <div id="version-comparison-controls" class="grid shrink-0 gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:grid-cols-2"><label class="label" for="version-from">Earlier version<select id="version-from" class="input mt-1" data-version-from></select></label><label class="label" for="version-to">Later version<select id="version-to" class="input mt-1" data-version-to></select></label></div>
                <div id="version-diff" class="min-h-0 flex-1 overflow-y-auto p-4"><p class="mb-3 text-xs text-slate-500">Removed text is red; added text is green.</p><div id="version-diff-output" class="whitespace-pre-wrap rounded-md border border-slate-200 bg-slate-50 p-4 font-mono text-sm leading-relaxed dark:border-slate-700 dark:bg-slate-950" data-version-diff-output></div></div>
                <footer class="shrink-0 border-t border-slate-200 p-4 dark:border-slate-800"><button type="button" class="btn rounded-md" data-version-restore-open>Restore selected earlier version</button><div id="version-restore-choice" class="mt-3 hidden grid gap-3 sm:grid-cols-2" data-version-restore-choice><button type="button" class="rounded-md border border-rose-300 p-3 text-left hover:bg-rose-50 dark:border-rose-800 dark:hover:bg-rose-950" data-version-restore-mode="undo"><strong class="block text-rose-700 dark:text-rose-300">Undo to this version</strong><span class="mt-1 block text-xs text-slate-500">Restore it and permanently remove every newer version.</span></button><button type="button" class="rounded-md border border-emerald-300 p-3 text-left hover:bg-emerald-50 dark:border-emerald-800 dark:hover:bg-emerald-950" data-version-restore-mode="preserve"><strong class="block text-emerald-700 dark:text-emerald-300">Add as latest</strong><span class="mt-1 block text-xs text-slate-500">Copy it to the newest version and preserve all history.</span></button></div></footer>
            </section>
        </dialog>
        <form id="version-restore-form" method="POST" class="hidden" data-version-restore-form>@csrf<input type="hidden" name="mode" data-version-restore-mode-field></form>
        <script type="application/json" id="note-version-data">@json($versionPayload)</script>
    @endif
@endsection
