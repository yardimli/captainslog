<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\GoalSource;
use App\Models\LogBlock;
use App\Models\TaskEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class GoalProgressService
{
    public function syncForUser(User $user): void
    {
        $user->goals()->with('sources')->get()->each(fn (Goal $goal) => $this->sync($goal));
    }

    public function sync(Goal $goal): void
    {
        $goal->loadMissing('sources');
        foreach ($goal->sources as $source) {
            if ($source->type === 'event' && $source->task_definition_id) {
                $this->syncEvents($goal, $source);
            }
            if ($source->type === 'github' && filled($source->github_project)) {
                $this->syncGithub($goal, $source);
            }
        }
        $this->refreshCompletion($goal);
    }

    private function syncEvents(Goal $goal, GoalSource $source): void
    {
        TaskEvent::query()->where('task_definition_id', $source->task_definition_id)
            ->whereHas('dailyLog', fn ($query) => $query->where('user_id', $goal->user_id))
            ->when($goal->start_date, fn ($query) => $query->where('occurred_at', '>=', $goal->start_date->startOfDay()))
            ->when($goal->end_date, fn ($query) => $query->where('occurred_at', '<=', $goal->end_date->endOfDay()))
            ->each(fn (TaskEvent $event) => $goal->entries()->updateOrCreate(
                ['external_key' => 'task-event:'.$event->id],
                ['goal_source_id' => $source->id, 'occurred_at' => $event->occurred_at, 'points' => 1, 'note' => $event->task_name]
            ));
    }

    private function syncGithub(Goal $goal, GoalSource $source): void
    {
        LogBlock::query()->where('type', 'sensor_github')
            ->whereHas('dailyLog', fn ($query) => $query->where('user_id', $goal->user_id))
            ->when($goal->start_date, fn ($query) => $query->whereHas('dailyLog', fn ($log) => $log->whereDate('log_date', '>=', $goal->start_date)))
            ->when($goal->end_date, fn ($query) => $query->whereHas('dailyLog', fn ($log) => $log->whereDate('log_date', '<=', $goal->end_date)))
            ->get()->each(function (LogBlock $block) use ($goal, $source) {
                foreach (data_get($block->metadata, 'commits', []) as $commit) {
                    $names = [data_get($commit, 'project'), data_get($commit, 'repository')];
                    if (! collect($names)->filter()->contains(fn ($name) => mb_strtolower($name) === mb_strtolower($source->github_project))) {
                        continue;
                    }
                    $sha = data_get($commit, 'sha');
                    if (! $sha) {
                        continue;
                    }
                    $occurredAt = Carbon::parse(data_get($commit, 'occurred_at', $block->occurred_at ?? $block->created_at));
                    if ($goal->start_date && $occurredAt->lt($goal->start_date->startOfDay())) {
                        continue;
                    }
                    if ($goal->end_date && $occurredAt->gt($goal->end_date->endOfDay())) {
                        continue;
                    }
                    $goal->entries()->updateOrCreate(
                        ['external_key' => 'github:'.$sha],
                        ['goal_source_id' => $source->id, 'occurred_at' => $occurredAt, 'points' => 1, 'note' => data_get($commit, 'message') ?: $source->github_project]
                    );
                }
            });
    }

    public function snapshot(Goal $goal, CarbonInterface $day, int $weekStartsOn = 1): array
    {
        [$periodStart, $periodEnd] = $this->periodBounds($goal, $day, $weekStartsOn);
        $query = $goal->entries()->with('source')->orderByDesc('occurred_at');
        if ($periodStart) {
            $query->where('occurred_at', '>=', $periodStart);
        }
        $asOf = Carbon::parse($day)->endOfDay();
        $query->where('occurred_at', '<=', $periodEnd && $periodEnd->lt($asOf) ? $periodEnd : $asOf);
        $entries = $query->get();
        $points = (int) $entries->sum('points');

        return [
            'goal' => $goal,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'points' => $points,
            'target' => $goal->target_points,
            'percent' => min(100, (int) round(($points / max(1, $goal->target_points)) * 100)),
            'complete' => $points >= $goal->target_points,
            'latest' => $entries->first(),
            'entries' => $entries,
        ];
    }

    public function history(Goal $goal, CarbonInterface $through, int $weekStartsOn = 1, int $limit = 12): Collection
    {
        if ($goal->period === 'none') {
            return collect([$this->snapshot($goal, $through, $weekStartsOn)]);
        }
        $cursor = Carbon::parse($through)->startOfDay();

        return collect(range(1, $limit))->map(function () use ($goal, &$cursor, $weekStartsOn) {
            $snapshot = $this->snapshot($goal, $cursor, $weekStartsOn);
            $cursor = match ($goal->period) {
                'daily' => $cursor->subDay(),
                'monthly' => $cursor->subMonthNoOverflow(),
                default => $cursor->subWeek(),
            };

            return $snapshot;
        })->filter(fn ($snapshot) => ! $goal->start_date || ! $snapshot['period_end'] || $snapshot['period_end']->gte($goal->start_date))->values();
    }

    private function periodBounds(Goal $goal, CarbonInterface $day, int $weekStartsOn): array
    {
        $day = Carbon::parse($day)->startOfDay();
        [$start, $end] = match ($goal->period) {
            'daily' => [$day->copy(), $day->copy()->endOfDay()],
            'monthly' => [$day->copy()->startOfMonth(), $day->copy()->endOfMonth()],
            'none' => [$goal->start_date?->copy()->startOfDay(), $goal->end_date?->copy()->endOfDay()],
            default => [$day->copy()->startOfWeek($weekStartsOn), $day->copy()->endOfWeek(($weekStartsOn + 6) % 7)],
        };
        if ($goal->start_date && (! $start || $start->lt($goal->start_date))) {
            $start = $goal->start_date->copy()->startOfDay();
        }
        if ($goal->end_date && (! $end || $end->gt($goal->end_date))) {
            $end = $goal->end_date->copy()->endOfDay();
        }

        return [$start, $end];
    }

    private function refreshCompletion(Goal $goal): void
    {
        if ($goal->period !== 'none') {
            if ($goal->completed_at) {
                $goal->update(['completed_at' => null]);
            }

            return;
        }
        $points = 0;
        $completedAt = null;
        foreach ($goal->entries()->orderBy('occurred_at')->get() as $entry) {
            $points += $entry->points;
            if ($points >= $goal->target_points) {
                $completedAt = $entry->occurred_at;
                break;
            }
        }
        $completionChanged = ($goal->completed_at === null) !== ($completedAt === null)
            || ($goal->completed_at && $completedAt && ! $goal->completed_at->equalTo($completedAt));
        if ($completionChanged) {
            $goal->update(['completed_at' => $completedAt]);
        }
    }
}
