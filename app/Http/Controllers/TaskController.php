<?php

namespace App\Http\Controllers;

use App\Models\TaskDefinition;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        return view('tasks.index', ['tasks' => TaskDefinition::where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $task = $request->user()->taskDefinitions()->create($data);

        return redirect()->route('tasks.index')->with('status', 'Task created.');
    }

    public function update(Request $request, TaskDefinition $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $task->update($this->validated($request));

        return redirect()->route('tasks.index')->with('status', 'Task updated.');
    }

    public function destroy(Request $request, TaskDefinition $task)
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        $task->update(['is_active' => false]);

        return redirect()->route('tasks.index')->with('status', 'Task archived.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate(['name' => 'required|string|max:80', 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'options_text' => 'nullable|string|max:1000']);
        $options = collect(preg_split('/[\r\n,]+/', $data['options_text'] ?? ''))->map(fn ($v) => trim($v))->filter()->unique()->values()->all();

        return ['name' => $data['name'], 'color' => strtolower($data['color']), 'is_sticky' => $request->boolean('is_sticky'), 'options' => $options ?: null, 'is_active' => true];
    }
}
