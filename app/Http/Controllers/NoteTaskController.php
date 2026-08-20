<?php

namespace App\Http\Controllers;

use App\Models\NoteTask;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteTaskController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'note_id' => ['nullable', 'integer', Rule::exists('notes', 'id')->where(fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('deleted_at'))],
        ]);
        $request->user()->noteTasks()->create($data + [
            'position' => ((int) $request->user()->noteTasks()->max('position')) + 1,
        ]);

        return back()->with('status', 'Task added.');
    }

    public function update(Request $request, NoteTask $noteTask)
    {
        abort_unless($noteTask->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);
        if (array_key_exists('is_completed', $data)) {
            $data['completed_at'] = $data['is_completed'] ? now() : null;
        }
        $noteTask->update($data);

        return back()->with('status', 'Task updated.');
    }

    public function destroy(Request $request, NoteTask $noteTask)
    {
        abort_unless($noteTask->user_id === $request->user()->id, 403);
        $noteTask->delete();

        return back()->with('status', 'Task deleted.');
    }
}
