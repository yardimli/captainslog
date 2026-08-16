<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use App\Services\GuestDemoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestDemoController extends Controller
{
    public function __construct(private GuestDemoService $demo) {}

    public function index(Request $request)
    {
        $user = $this->demo->account($request);
        $days = collect(range(7, 0))->map(fn ($daysAgo) => today()->subDays($daysAgo));
        $requested = rescue(fn () => Carbon::parse($request->query('date'))->startOfDay(), today(), false);
        $day = $days->contains(fn ($item) => $item->isSameDay($requested)) ? $requested : today();
        $log = DailyLog::where('user_id', $user->id)->whereDate('log_date', $day)->firstOrFail();
        $log->load(['blocks.attachments', 'blocks.taskEvent']);
        $logs = DailyLog::where('user_id', $user->id)->whereBetween('log_date', [$days->first(), $days->last()])->withCount('blocks')->get()->keyBy(fn ($item) => $item->log_date->toDateString());
        $tasks = TaskDefinition::where('user_id', $user->id)->where('is_active', true)->where('is_sticky', true)->get()->filter(fn (TaskDefinition $task) => $task->occursOn($day));
        $counts = $log->taskEvents()->selectRaw('task_definition_id, count(*) as total')->groupBy('task_definition_id')->pluck('total', 'task_definition_id');

        return view('welcome', compact('day', 'days', 'log', 'logs', 'tasks', 'counts'));
    }

    public function storeBlock(Request $request, DailyLog $dailyLog)
    {
        $user = $this->demo->account($request);
        abort_unless($dailyLog->user_id === $user->id, 403);
        $data = $request->validate(['content' => 'required|string|max:100000']);
        $block = $dailyLog->blocks()->create(['type' => 'text', 'content' => $data['content'], 'metadata' => ['demo' => true], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

        return response()->json(['message' => 'Demo entry added.', 'block' => $block, 'reload' => true], 201);
    }

    public function updateBlock(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        abort_if($block->type === 'event', 422, 'Event entries cannot be edited in the demo.');
        $block->update($request->validate(['content' => 'required|string|max:100000']));

        return response()->json(['message' => 'Demo entry updated.']);
    }

    public function destroyBlock(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        $block->delete();

        return response()->json(['message' => 'Demo entry deleted.']);
    }

    public function storeEvent(Request $request, DailyLog $dailyLog, TaskDefinition $task)
    {
        $user = $this->demo->account($request);
        abort_unless($dailyLog->user_id === $user->id && $task->user_id === $user->id, 403);
        abort_unless($task->is_active && $task->occursOn($dailyLog->log_date), 422, 'This event is not scheduled for this day.');
        $data = $request->validate(['value' => [empty($task->options) ? 'nullable' : 'required', 'nullable', 'string', 'max:100']]);
        if (! empty($task->options) && ! in_array($data['value'] ?? null, $task->options, true)) {
            abort(422, 'Choose a valid value.');
        }
        $event = DB::transaction(function () use ($dailyLog, $task, $data) {
            $block = $dailyLog->blocks()->create(['type' => 'event', 'metadata' => ['demo' => true], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

            return TaskEvent::create(['daily_log_id' => $dailyLog->id, 'task_definition_id' => $task->id, 'log_block_id' => $block->id, 'task_name' => $task->name, 'selected_value' => $data['value'] ?? null, 'occurred_at' => now()]);
        });

        return response()->json(['message' => "$task->name logged in your private demo.", 'event' => $event, 'count' => $dailyLog->taskEvents()->where('task_definition_id', $task->id)->count()], 201);
    }

    private function authorizeBlock(Request $request, LogBlock $block): void
    {
        $user = $this->demo->account($request);
        abort_unless($block->dailyLog()->where('user_id', $user->id)->exists(), 403);
    }
}
