<?php

namespace App\Http\Controllers;

use App\Models\TaskDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'options_text' => 'nullable|string|max:1000',
            'recurrence_type' => 'required|in:daily,weekly,monthly',
            'weekdays' => 'nullable|array',
            'weekdays.*' => 'integer|between:1,7',
            'month_days_text' => 'nullable|string|max:200',
            'scheduled_times_text' => 'nullable|string|max:300',
        ]);
        $options = collect(preg_split('/[\r\n,]+/', $data['options_text'] ?? ''))->map(fn ($v) => trim($v))->filter()->unique()->values()->all();
        $recurrenceDays = match ($data['recurrence_type']) {
            'weekly' => collect($data['weekdays'] ?? [])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all(),
            'monthly' => collect(preg_split('/[\s,]+/', $data['month_days_text'] ?? ''))->filter()->map(fn ($day) => (int) $day)->filter(fn ($day) => $day >= 1 && $day <= 31)->unique()->sort()->values()->all(),
            default => null,
        };
        $scheduledTimes = collect(preg_split('/[\s,]+/', $data['scheduled_times_text'] ?? ''))
            ->map(fn ($time) => trim($time))->filter()->unique()->sort()->values();

        if (in_array($data['recurrence_type'], ['weekly', 'monthly'], true) && empty($recurrenceDays)) {
            throw ValidationException::withMessages(['recurrence_type' => 'Choose at least one day for this recurrence.']);
        }
        if ($scheduledTimes->contains(fn ($time) => ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time))) {
            throw ValidationException::withMessages(['scheduled_times_text' => 'Use 24-hour times such as 08:30 or 17:00.']);
        }
        if ($request->boolean('is_sticky') && $scheduledTimes->isEmpty()) {
            throw ValidationException::withMessages(['scheduled_times_text' => 'Add at least one time slot for a sticky event.']);
        }

        return [
            'name' => $data['name'],
            'color' => strtolower($data['color']),
            'is_sticky' => $request->boolean('is_sticky'),
            'recurrence_type' => $data['recurrence_type'],
            'recurrence_days' => $recurrenceDays ?: null,
            'scheduled_times' => $scheduledTimes->all() ?: null,
            'options' => $options ?: null,
            'is_active' => true,
        ];
    }
}
