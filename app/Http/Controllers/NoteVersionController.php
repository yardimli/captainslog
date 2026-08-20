<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoteVersionController extends Controller
{
    public function restore(Request $request, Note $note, NoteVersion $version)
    {
        $this->authorize('update', $note);
        abort_unless($version->note_id === $note->id, 404);
        $data = $request->validate(['mode' => ['required', 'in:undo,preserve']]);

        DB::transaction(function () use ($request, $note, $version, $data) {
            $attributes = [
                'title' => $version->title,
                'content' => $version->content,
                'content_json' => $version->content_json,
                'plain_text' => $version->plain_text,
                'excerpt' => (string) str($version->plain_text)->squish()->limit(280, ''),
                'content_format' => $version->content_format,
                'color' => $version->color,
            ];

            if ($data['mode'] === 'undo') {
                $note->versions()->where('version_number', '>', $version->version_number)->delete();
                $note->update($attributes);

                return;
            }

            $note->update($attributes);
            $note->versions()->create([
                'created_by_user_id' => $request->user()->id,
                'version_number' => ((int) $note->versions()->max('version_number')) + 1,
                ...$version->only(['title', 'content', 'content_json', 'plain_text', 'content_format', 'color']),
                'change_source' => 'restored_copy',
                'change_summary' => "Restored from version {$version->version_number} while preserving history.",
            ]);
        });

        $message = $data['mode'] === 'undo'
            ? "Undid changes after version {$version->version_number}."
            : "Added version {$version->version_number} as the latest version.";

        return redirect()->route('notes.show', $note)->with('status', $message);
    }
}
