<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\Notebook;
use App\Models\NoteUserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $workspace = $this->workspace($request);
        $notes = $workspace['notes'];
        $note = in_array($workspace['currentView'], ['tasks', 'tags', 'trash'], true) ? null : $notes->first();
        $note?->load('versions')->loadCount('versions');

        return view('notes.index', $workspace + [
            'note' => $note,
            'creating' => ! $note && ! in_array($workspace['currentView'], ['tasks', 'tags', 'trash'], true),
        ]);
    }

    public function create(Request $request)
    {
        $workspace = $this->workspace($request);

        return view('notes.index', $workspace + ['note' => null, 'creating' => true, 'currentView' => 'notes']);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $note = DB::transaction(function () use ($request, $data) {
            $note = $request->user()->notes()->create($this->noteAttributes($data));
            $note->tags()->sync($data['tag_ids'] ?? []);
            $this->createVersion($note, $request->user()->id, 'created');

            return $note;
        });

        if ($request->expectsJson()) {
            return response()->json($this->autosaveResponse($note), 201);
        }

        return redirect()->route('notes.show', $note)->with('status', 'Note created.');
    }

    public function show(Request $request, Note $note)
    {
        $this->authorize('view', $note);
        $note->updateQuietly(['last_viewed_at' => now()]);
        $note->load(['versions', 'tags', 'tasks'])->loadCount('versions');
        $workspace = $this->workspace($request);

        return view('notes.index', $workspace + compact('note') + ['creating' => false, 'currentView' => 'notes']);
    }

    public function update(Request $request, Note $note)
    {
        $this->authorize('update', $note);
        $data = $this->validated($request);
        $changed = DB::transaction(function () use ($request, $note, $data) {
            $note->update($this->noteAttributes($data));
            $tagChanges = $note->tags()->sync($data['tag_ids'] ?? []);
            if (! $note->wasChanged() && ! collect($tagChanges)->flatten()->isNotEmpty()) {
                return false;
            }

            $this->createVersion($note->fresh(), $request->user()->id, $request->expectsJson() ? 'autosave' : 'manual_save');

            return true;
        });

        if ($request->expectsJson()) {
            return response()->json($this->autosaveResponse($note->fresh()) + ['changed' => $changed]);
        }

        return redirect()->route('notes.show', $note)->with('status', 'Note saved.');
    }

    public function destroy(Note $note)
    {
        $this->authorize('delete', $note);
        $note->delete();

        return redirect()->route('notes.index')->with('status', 'Note moved to Trash.');
    }

    private function workspace(Request $request): array
    {
        $defaultNotebook = Notebook::firstOrCreate(
            ['user_id' => $request->user()->id, 'name' => 'Notes'],
            ['is_default' => true],
        );
        NoteUserSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['default_notebook_id' => $defaultNotebook->id],
        );
        $notebooks = $request->user()->notebooks()
            ->withCount(['notes' => fn ($query) => $query->where('is_archived', false)])
            ->orderByDesc('is_default')->orderBy('position')->orderBy('name')->get();
        $activeNotebook = $notebooks->firstWhere('id', (int) $request->query('notebook'));
        $tags = $request->user()->noteTags()->withCount('notes')->orderBy('name')->get();
        $activeTag = $tags->firstWhere('id', (int) $request->query('tag'));
        $currentView = in_array($request->query('view'), ['tasks', 'tags', 'trash'], true) ? $request->query('view') : 'notes';
        $activeNotesQuery = $request->user()->notes()->with(['notebook', 'tags'])->withCount(['versions', 'tasks'])->where('is_archived', false);
        $allNotesCount = (clone $activeNotesQuery)->count();
        $trashCount = $request->user()->notes()->onlyTrashed()->count();

        if ($currentView === 'trash') {
            $notes = $request->user()->notes()->onlyTrashed()->with(['notebook', 'tags'])->latest('deleted_at')->get();
        } else {
            if ($activeNotebook) {
                $activeNotesQuery->where('notebook_id', $activeNotebook->id);
            }
            if ($activeTag) {
                $activeNotesQuery->whereHas('tags', fn ($query) => $query->whereKey($activeTag->id));
            }
            $notes = $activeNotesQuery->latest('updated_at')->get();
        }

        return [
            'notebooks' => $notebooks,
            'notes' => $notes,
            'activeNotebook' => $activeNotebook,
            'activeTag' => $activeTag,
            'allNotesCount' => $allNotesCount,
            'trashCount' => $trashCount,
            'tags' => $tags,
            'noteTasks' => $request->user()->noteTasks()->with('note')->orderBy('is_completed')->orderBy('position')->latest('id')->get(),
            'currentView' => $currentView,
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'notebook_id' => [
                'required',
                'integer',
                Rule::exists('notebooks', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('deleted_at')),
            ],
            'title' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'content_json' => ['nullable', 'json', 'max:2000000'],
            'plain_text' => ['nullable', 'string', 'max:1000000'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tag_ids' => ['nullable', 'array', 'max:30'],
            'tag_ids.*' => ['integer', Rule::exists('note_tags', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id))],
        ]);
    }

    private function noteAttributes(array $data): array
    {
        $content = trim($data['content'] ?? '');
        $plainText = trim($data['plain_text'] ?? html_entity_decode(strip_tags($content)));

        return [
            'notebook_id' => $data['notebook_id'],
            'title' => trim($data['title'] ?? '') ?: 'Untitled',
            'content' => $content,
            'content_json' => filled($data['content_json'] ?? null) ? json_decode($data['content_json'], true) : null,
            'plain_text' => $plainText,
            'excerpt' => Str::limit(preg_replace('/\s+/', ' ', $plainText), 280, ''),
            'content_format' => filled($data['content_json'] ?? null) ? 'tiptap' : 'text',
            'color' => strtolower($data['color'] ?? '#6366f1'),
            'source_type' => 'manual',
        ];
    }

    private function createVersion(Note $note, int $userId, string $source): void
    {
        $note->versions()->create([
            'created_by_user_id' => $userId,
            'version_number' => ((int) $note->versions()->max('version_number')) + 1,
            'title' => $note->title,
            'content' => $note->content,
            'content_json' => $note->content_json,
            'plain_text' => $note->plain_text,
            'content_format' => $note->content_format,
            'color' => $note->color,
            'change_source' => $source,
        ]);
    }

    private function autosaveResponse(Note $note): array
    {
        return [
            'message' => 'Saved',
            'note_id' => $note->id,
            'title' => $note->title,
            'show_url' => route('notes.show', ['note' => $note, 'notebook' => $note->notebook_id]),
            'update_url' => route('notes.update', $note),
            'ai_url' => route('notes.ai', $note),
            'saved_at' => $note->updated_at?->toIso8601String(),
        ];
    }
}
