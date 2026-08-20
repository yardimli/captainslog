<?php

namespace App\Http\Controllers;

use App\Models\NoteTag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteTagController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('note_tags')->where('user_id', $request->user()->id)],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $request->user()->noteTags()->create($data);

        return back()->with('status', 'Tag created.');
    }

    public function destroy(Request $request, NoteTag $noteTag)
    {
        abort_unless($noteTag->user_id === $request->user()->id, 403);
        $noteTag->delete();

        return redirect()->route('notes.index', ['view' => 'tags'])->with('status', 'Tag deleted.');
    }
}
