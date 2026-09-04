<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Services\GoalProgressService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GoalEntryController extends Controller
{
    public function __construct(private GoalProgressService $progress) {}

    public function store(Request $request, Goal $goal)
    {
        abort_unless($goal->user_id === $request->user()->id, 403);
        abort_unless($goal->manual_enabled, 422, 'Manual progress is not enabled for this goal.');
        $data = $request->validate(['points' => 'required|integer|min:1|max:1000000', 'note' => 'nullable|string|max:2000', 'occurred_on' => 'required|date']);
        $occurredAt = Carbon::parse($data['occurred_on'])->setTimeFrom(now());
        abort_unless($goal->isAvailableOn($occurredAt), 422, 'This date is outside the goal.');
        $goal->entries()->create(['occurred_at' => $occurredAt, 'points' => $data['points'], 'note' => $data['note'] ?? null]);
        $this->progress->sync($goal);

        return redirect()->route('goals.show', ['goal' => $goal, 'date' => $data['occurred_on']])->with('status', 'Goal progress added.');
    }
}
