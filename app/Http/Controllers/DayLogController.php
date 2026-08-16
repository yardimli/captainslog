<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DayLogController extends Controller
{
    public function show(Request $request, string $date)
    {
        $day = Carbon::parse($date)->startOfDay();
        $log = DailyLog::where('user_id', $request->user()->id)->whereDate('log_date', $day)->first();
        if (! $log) {
            $log = DailyLog::create(['user_id' => $request->user()->id, 'log_date' => $day]);
        }
        $log->load(['blocks.attachments', 'blocks.taskEvent', 'apiCalls']);
        $tasks = TaskDefinition::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (TaskDefinition $task) => $task->occursOn($day))
            ->values();
        $counts = $log->taskEvents()->selectRaw('task_definition_id, count(*) as total')->groupBy('task_definition_id')->pluck('total', 'task_definition_id');
        $timeline = collect(range(0, 23))->mapWithKeys(fn ($hour) => [$hour => collect()]);

        foreach ($log->blocks as $block) {
            $occurredAt = $block->taskEvent?->occurred_at ?? $block->created_at;
            $timeline[$occurredAt->hour]->push([
                'kind' => 'block',
                'time' => $occurredAt->format('H:i'),
                'sort' => $occurredAt->format('H:i:s').'-1-'.$block->id,
                'block' => $block,
            ]);
        }

        foreach ($tasks->where('is_sticky', true) as $task) {
            $times = $task->scheduled_times ?: ['00:00'];
            foreach ($times as $time) {
                $hour = (int) substr($time, 0, 2);
                $timeline[$hour]->push([
                    'kind' => 'schedule',
                    'time' => $time,
                    'sort' => $time.':00-0-'.$task->id,
                    'task' => $task,
                    'is_unscheduled' => empty($task->scheduled_times),
                ]);
            }
        }

        $timeline = $timeline->map(fn ($items) => $items->sortBy('sort')->values());

        return view('logs.show', compact('day', 'log', 'tasks', 'counts', 'timeline'));
    }
}
