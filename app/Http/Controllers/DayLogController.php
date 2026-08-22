<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Services\GithubSensorSync;
use App\Services\BrowsingActivityRecorder;
use App\Services\GoogleCalendarSync;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DayLogController extends Controller
{
    public function __construct(private GithubSensorSync $githubSensor, private BrowsingActivityRecorder $browsingRecorder, private GoogleCalendarSync $googleCalendar) {}

    public function show(Request $request, string $date)
    {
        $startedAt = hrtime(true);
        $day = Carbon::parse($date)->startOfDay();
        $log = DailyLog::where('user_id', $request->user()->id)->whereDate('log_date', $day)->first();
        if (! $log) {
            $log = DailyLog::create(['user_id' => $request->user()->id, 'log_date' => $day]);
        }
        if ($day->format('Y-m') === now()->format('Y-m')) {
            $this->googleCalendar->syncUser($request->user());
            $log = $log->fresh();
        }
        $this->githubSensor->sync($request->user(), $log, $day);
        $this->browsingRecorder->finalizeStale($request->user());
        $showHidden = $request->boolean('show_hidden');
        $log->load(['blocks.attachments', 'blocks.taskEvent', 'blocks.browsingActivities']);
        $tasks = TaskDefinition::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (TaskDefinition $task) => $task->occursOn($day))
            ->values();
        $counts = $log->taskEvents()->selectRaw('task_definition_id, count(*) as total')->groupBy('task_definition_id')->pluck('total', 'task_definition_id');
        $slotCounts = $log->taskEvents()
            ->whereNotNull('scheduled_time')
            ->selectRaw('task_definition_id, scheduled_time, count(*) as total')
            ->groupBy('task_definition_id', 'scheduled_time')
            ->get()
            ->groupBy('task_definition_id')
            ->map(fn ($events) => $events->pluck('total', 'scheduled_time')->map(fn ($count) => (int) $count));
        $timelineItems = collect();

        foreach ($log->blocks as $block) {
            if ($block->is_hidden && ! $showHidden) {
                continue;
            }
            $occurredAt = $block->taskEvent?->occurred_at ?? $block->occurred_at ?? $block->created_at;
            $timelineItems->push([
                'kind' => 'block',
                'time' => $occurredAt->format('H:i'),
                'minute' => ($occurredAt->hour * 60) + $occurredAt->minute,
                'sort' => $occurredAt->format('H:i:s').'-1-'.$block->id,
                'block' => $block,
                'is_hidden' => $block->is_hidden,
            ]);
        }

        $incompleteStickyTasks = $tasks->where('is_sticky', true)->filter(function (TaskDefinition $task) use ($counts, $slotCounts) {
            $times = $task->scheduled_times ?? [];
            if (! $times) {
                return (int) ($counts[$task->id] ?? 0) < $task->daily_default_count;
            }

            return collect($times)->contains(fn ($time) => (int) data_get($slotCounts, $task->id.'.'.$time, 0) < $task->daily_default_count);
        });
        $plannerTasks = $incompleteStickyTasks->filter(fn (TaskDefinition $task) => $task->isPlannerVisible($day, now()));
        foreach ($plannerTasks as $task) {
            $times = $task->scheduled_times ?: ['00:00'];
            foreach ($times as $time) {
                if (! empty($task->scheduled_times) && (int) data_get($slotCounts, $task->id.'.'.$time, 0) >= $task->daily_default_count) {
                    continue;
                }
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $timelineItems->push([
                    'kind' => 'schedule',
                    'time' => $time,
                    'minute' => ($hour * 60) + $minute,
                    'sort' => $time.':00-0-'.$task->id,
                    'task' => $task,
                    'is_unscheduled' => empty($task->scheduled_times),
                ]);
            }
        }

        $nextStickyVisibility = $day->isToday()
            ? $incompleteStickyTasks
                ->pluck('visible_after')
                ->filter(fn ($time) => $time && $time > now()->format('H:i'))
                ->sort()
                ->first()
            : null;

        $itemsByMinute = $timelineItems->sortBy('sort')->groupBy('minute');
        $currentMinute = $day->isToday() ? (now()->hour * 60) + now()->minute : null;
        $positions = $itemsByMinute->keys();
        if ($currentMinute !== null) {
            $positions->push($currentMinute);
        }
        $positions = $positions->push(1440)->unique()->sort()->values();
        $cursor = 0;
        $formatMinute = fn (int $minute) => $minute === 1440 ? '24:00' : sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
        $gapState = function (int $end) use ($day, $currentMinute): string {
            if ($day->isBefore(today())) {
                return 'past';
            }
            if ($day->isAfter(today())) {
                return 'future';
            }

            return $end <= $currentMinute ? 'past' : 'future';
        };
        $timeline = collect();

        foreach ($positions as $position) {
            $position = (int) $position;
            if ($position > $cursor) {
                $timeline->push([
                    'kind' => 'gap',
                    'from' => $formatMinute($cursor),
                    'to' => $formatMinute($position),
                    'state' => $gapState($position),
                ]);
            }
            if ($currentMinute !== null && $position === $currentMinute) {
                $timeline->push(['kind' => 'now', 'time' => $formatMinute($currentMinute)]);
            }
            foreach ($itemsByMinute->get($position, collect()) as $item) {
                $timeline->push($item);
            }
            $cursor = $position;
        }

        $mainFragment = $request->header('X-Day-View') === 'main';

        return response()->view('logs.show', compact('day', 'log', 'tasks', 'counts', 'slotCounts', 'timeline', 'showHidden', 'mainFragment', 'nextStickyVisibility'))
            ->header('Server-Timing', sprintf('day-view;dur=%.1f', (hrtime(true) - $startedAt) / 1_000_000));
    }
}
