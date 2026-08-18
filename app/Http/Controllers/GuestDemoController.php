<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use App\Services\GuestDemoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $counts = $log->taskEvents()->selectRaw('task_definition_id, count(*) as total')->groupBy('task_definition_id')->pluck('total', 'task_definition_id');
        $tasks = TaskDefinition::where('user_id', $user->id)->where('is_active', true)->where('is_sticky', true)->get()->filter(fn (TaskDefinition $task) =>
            $task->occursOn($day)
            && $task->isPlannerVisible($day, now())
            && (int) ($counts[$task->id] ?? 0) < $task->daily_default_count
        );

        return view('welcome', compact('day', 'days', 'log', 'logs', 'tasks', 'counts'));
    }

    public function storeBlock(Request $request, DailyLog $dailyLog)
    {
        $user = $this->demo->account($request);
        abort_unless($dailyLog->user_id === $user->id, 403);
        $data = $request->validate(['content' => 'required|string|max:100000', 'emoji' => 'nullable|string|max:32', 'occurred_at' => 'nullable|date_format:H:i']);
        $occurredAt = $dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at'] ?? now()->format('H:i'));
        $block = $dailyLog->blocks()->create(['type' => 'text', 'emoji' => $data['emoji'] ?? LogBlock::defaultEmojiForType('text'), 'content' => $data['content'], 'occurred_at' => $occurredAt, 'metadata' => ['demo' => true], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

        return response()->json(['message' => 'Demo entry added.', 'block' => $block, 'reload' => true], 201);
    }

    public function updateBlock(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        abort_if($block->type === 'event', 422, 'Event entries cannot be edited in the demo.');
        $data = $request->validate(['content' => 'required|string|max:100000', 'emoji' => 'nullable|string|max:32', 'occurred_at' => 'nullable|date_format:H:i']);
        if (isset($data['occurred_at'])) {
            $data['occurred_at'] = $block->dailyLog->log_date->copy()->setTimeFromTimeString($data['occurred_at']);
        }
        $block->update($data);

        return response()->json(['message' => 'Demo entry updated.']);
    }

    public function destroyBlock(Request $request, LogBlock $block)
    {
        $this->authorizeBlock($request, $block);
        $block->attachments->each(function ($attachment) {
            if (! data_get($attachment->metadata, 'shared_demo_asset')) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
            $attachment->delete();
        });
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
            $block = $dailyLog->blocks()->create(['type' => 'event', 'emoji' => $task->emoji, 'metadata' => ['demo' => true], 'position' => ($dailyLog->blocks()->max('position') ?? 0) + 1]);

            return TaskEvent::create(['daily_log_id' => $dailyLog->id, 'task_definition_id' => $task->id, 'log_block_id' => $block->id, 'task_name' => $task->name, 'selected_value' => $data['value'] ?? null, 'occurred_at' => now()]);
        });

        return response()->json(['message' => "$task->name logged in your private demo.", 'event' => $event, 'emoji' => $task->emoji, 'count' => $dailyLog->taskEvents()->where('task_definition_id', $task->id)->count()], 201);
    }

    public function showAttachment(Request $request, Attachment $attachment)
    {
        $user = $this->demo->account($request);
        abort_unless($attachment->user_id === $user->id, 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), ['Content-Type' => $attachment->mime_type]);
    }

    private function authorizeBlock(Request $request, LogBlock $block): void
    {
        $user = $this->demo->account($request);
        abort_unless($block->dailyLog()->where('user_id', $user->id)->exists(), 403);
    }
}
