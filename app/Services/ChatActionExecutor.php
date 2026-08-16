<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChatActionExecutor
{
    public function normalize(array $actions): array
    {
        if (empty($actions) || count($actions) > 10) {
            throw ValidationException::withMessages(['actions' => 'The assistant must propose between one and ten actions.']);
        }

        return collect($actions)->map(fn (array $action) => $this->normalizeAction($action))->values()->all();
    }

    public function describe(array $actions, ?User $user = null): string
    {
        return collect($actions)->map(function (array $action, int $index) use ($user) {
            $description = match ($action['type']) {
                'add_log_entry' => "Add a log entry on {$action['date']} at ".($user?->formatClock($action['time']) ?? $action['time']).": “{$action['content']}”",
                'create_event' => "Create the event “{$action['name']}” in {$action['color']}".$this->eventDetails($action, $user),
                'record_event' => "Record “{$action['event_name']}” on {$action['date']} at ".($user?->formatClock($action['time']) ?? $action['time']).
                    ($action['value'] !== null ? " with value “{$action['value']}”" : '').
                    ($action['notes'] !== null ? " and notes “{$action['notes']}”" : ''),
            };

            return ($index + 1).'. '.$description;
        })->implode("\n");
    }

    public function execute(User $user, array $actions): array
    {
        return DB::transaction(function () use ($user, $actions) {
            $results = [];
            foreach ($actions as $action) {
                $results[] = match ($action['type']) {
                    'add_log_entry' => $this->addLogEntry($user, $action),
                    'create_event' => $this->createEvent($user, $action),
                    'record_event' => $this->recordEvent($user, $action),
                };
            }

            return $results;
        });
    }

    private function normalizeAction(array $action): array
    {
        $type = $action['type'] ?? null;
        if (! in_array($type, ['add_log_entry', 'create_event', 'record_event'], true)) {
            throw ValidationException::withMessages(['actions' => 'The assistant proposed an unsupported action.']);
        }

        return match ($type) {
            'add_log_entry' => $this->validate($action, [
                'type' => 'required|in:add_log_entry', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i', 'content' => 'required|string|max:100000',
            ]),
            'record_event' => array_merge($this->validate($action, [
                'type' => 'required|in:record_event', 'event_name' => 'required|string|max:80', 'date' => 'required|date_format:Y-m-d', 'time' => 'required|date_format:H:i', 'value' => 'nullable|string|max:100', 'notes' => 'nullable|string|max:100000',
            ]), ['value' => $action['value'] ?? null, 'notes' => $action['notes'] ?? null]),
            'create_event' => $this->normalizeEvent($action),
        };
    }

    private function normalizeEvent(array $action): array
    {
        $data = $this->validate($action, [
            'type' => 'required|in:create_event', 'name' => 'required|string|max:80', 'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'options' => 'nullable|array|max:30', 'options.*' => 'string|max:100', 'recurrence_type' => 'required|in:daily,weekly,monthly',
            'recurrence_days' => 'nullable|array|max:31', 'recurrence_days.*' => 'integer|between:1,31',
            'scheduled_times' => 'nullable|array|max:24', 'scheduled_times.*' => 'date_format:H:i', 'is_sticky' => 'required|boolean',
        ]);
        $data['color'] = strtolower($data['color']);
        $data['options'] = collect($data['options'] ?? [])->unique()->values()->all() ?: null;
        $data['scheduled_times'] = collect($data['scheduled_times'] ?? [])->unique()->sort()->values()->all() ?: null;
        $data['recurrence_days'] = collect($data['recurrence_days'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all() ?: null;
        if ($data['recurrence_type'] === 'daily') {
            $data['recurrence_days'] = null;
        }
        $upperBound = $data['recurrence_type'] === 'weekly' ? 7 : 31;
        if ($data['recurrence_type'] !== 'daily' && (empty($data['recurrence_days']) || max($data['recurrence_days']) > $upperBound)) {
            throw ValidationException::withMessages(['actions' => 'The proposed event recurrence days are invalid.']);
        }
        if ($data['is_sticky'] && empty($data['scheduled_times'])) {
            throw ValidationException::withMessages(['actions' => 'A sticky event needs at least one time slot.']);
        }

        return $data;
    }

    private function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    private function eventDetails(array $action, ?User $user = null): string
    {
        $details = $action['recurrence_type'] === 'daily' ? ', every day' : ', '.$action['recurrence_type'].' on '.implode(', ', $action['recurrence_days']);
        if ($action['scheduled_times']) {
            $details .= ' at '.collect($action['scheduled_times'])->map(fn ($time) => $user?->formatClock($time) ?? $time)->implode(', ');
        }
        if ($action['options']) {
            $details .= ' with options '.implode(', ', $action['options']);
        }
        if ($action['is_sticky']) {
            $details .= ' as a sticky button';
        }

        return $details;
    }

    private function addLogEntry(User $user, array $action): array
    {
        $log = $this->dailyLog($user, $action['date']);
        $block = $log->blocks()->create([
            'type' => 'text', 'content' => $action['content'], 'position' => ($log->blocks()->max('position') ?? 0) + 1,
            'occurred_at' => Carbon::createFromFormat('Y-m-d H:i', $action['date'].' '.$action['time']),
            'metadata' => ['created_by' => 'smart_chat'],
        ]);

        return ['type' => 'add_log_entry', 'id' => $block->id, 'date' => $action['date']];
    }

    private function createEvent(User $user, array $action): array
    {
        $event = TaskDefinition::create([
            'user_id' => $user->id, 'name' => $action['name'], 'color' => $action['color'], 'options' => $action['options'],
            'recurrence_type' => $action['recurrence_type'], 'recurrence_days' => $action['recurrence_days'], 'scheduled_times' => $action['scheduled_times'],
            'is_sticky' => $action['is_sticky'], 'is_active' => true,
        ]);

        return ['type' => 'create_event', 'id' => $event->id, 'name' => $event->name];
    }

    private function recordEvent(User $user, array $action): array
    {
        $definition = TaskDefinition::where('user_id', $user->id)->where('is_active', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($action['event_name'])])->first();
        if (! $definition) {
            throw ValidationException::withMessages(['actions' => "The event “{$action['event_name']}” does not exist. Ask the assistant to create it first."]);
        }
        if ($definition->options && ! in_array($action['value'], $definition->options, true)) {
            throw ValidationException::withMessages(['actions' => 'Choose a configured value for '.$definition->name.': '.implode(', ', $definition->options)]);
        }
        $log = $this->dailyLog($user, $action['date']);
        $occurredAt = Carbon::createFromFormat('Y-m-d H:i', $action['date'].' '.$action['time']);
        $block = $log->blocks()->create([
            'type' => 'event', 'content' => $action['notes'], 'position' => ($log->blocks()->max('position') ?? 0) + 1,
            'occurred_at' => $occurredAt, 'metadata' => ['created_by' => 'smart_chat'],
        ]);
        $event = TaskEvent::create([
            'daily_log_id' => $log->id, 'task_definition_id' => $definition->id, 'log_block_id' => $block->id,
            'task_name' => $definition->name, 'selected_value' => $action['value'], 'occurred_at' => $occurredAt,
        ]);

        return ['type' => 'record_event', 'id' => $event->id, 'name' => $event->task_name, 'date' => $action['date']];
    }

    private function dailyLog(User $user, string $date): DailyLog
    {
        return DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->first()
            ?? DailyLog::create(['user_id' => $user->id, 'log_date' => $date]);
    }
}
