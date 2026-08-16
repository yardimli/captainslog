<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskEventController extends Controller
{
    public function store(Request $request, DailyLog $dailyLog, TaskDefinition $task)
    {
        abort_unless($dailyLog->user_id === $request->user()->id && $task->user_id === $request->user()->id, 403);
        abort_unless($task->is_active && $task->occursOn($dailyLog->log_date), 422, 'This event is not scheduled for this day.');
        $data = $request->validate(['value' => [empty($task->options) ? 'nullable' : 'required', 'nullable', 'string', 'max:100']]);
        if (! empty($task->options) && ! in_array($data['value'] ?? null, $task->options, true)) {
            abort(422, 'Choose a valid value.');
        }
        $event = DB::transaction(function () use ($dailyLog, $task, $data) {
            $block = $dailyLog->blocks()->create(['type' => 'event', 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

            return TaskEvent::create(['daily_log_id' => $dailyLog->id, 'task_definition_id' => $task->id, 'log_block_id' => $block->id, 'task_name' => $task->name, 'selected_value' => $data['value'] ?? null, 'occurred_at' => now()]);
        });

        return response()->json(['message' => "$task->name logged.", 'event' => $event, 'count' => $dailyLog->taskEvents()->where('task_definition_id', $task->id)->count(), 'notes_url' => route('events.edit', $event)], 201);
    }

    public function edit(Request $request, TaskEvent $event)
    {
        $this->authorizeEvent($request, $event);
        $event->load('block.attachments');

        return view('events.edit', compact('event'));
    }

    public function update(Request $request, TaskEvent $event)
    {
        $this->authorizeEvent($request, $event);
        $event->block->update(['content' => $request->validate(['notes' => 'nullable|string|max:100000'])['notes'] ?? null]);

        return redirect()->route('logs.show', $event->dailyLog->log_date->toDateString())->with('status', 'Event notes saved.');
    }

    private function authorizeEvent(Request $request, TaskEvent $event): void
    {
        abort_unless($event->dailyLog()->where('user_id', $request->user()->id)->exists(), 403);
    }
}
