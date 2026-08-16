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
        $tasks = TaskDefinition::where('user_id', $request->user()->id)->where('is_active', true)->orderBy('name')->get();
        $counts = $log->taskEvents()->selectRaw('task_definition_id, count(*) as total')->groupBy('task_definition_id')->pluck('total', 'task_definition_id');

        return view('logs.show', compact('day', 'log', 'tasks', 'counts'));
    }
}
