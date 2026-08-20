<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;

class NoteTrashController extends Controller
{
    public function restore(Request $request, int $noteId)
    {
        $note = $this->trashedNote($request, $noteId);
        $note->restore();

        return redirect()->route('notes.show', $note)->with('status', 'Note restored.');
    }

    public function destroy(Request $request, int $noteId)
    {
        $note = $this->trashedNote($request, $noteId);
        $note->forceDelete();

        return redirect()->route('notes.index', ['view' => 'trash'])->with('status', 'Note permanently deleted.');
    }

    private function trashedNote(Request $request, int $noteId): Note
    {
        return Note::onlyTrashed()->where('user_id', $request->user()->id)->findOrFail($noteId);
    }
}
