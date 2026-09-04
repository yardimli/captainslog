<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use App\Services\GoalProgressService;
use App\Services\GoogleCalendarSync;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(private GoogleCalendarSync $googleCalendar, private GoalProgressService $goalProgress) {}

    public function index(Request $request, ?string $date = null)
    {
        $focus = rescue(fn () => Carbon::parse($date ?: now()), now(), false)->startOfDay();
        if (! $request->user()->is_guest && $focus->format('Y-m') === now()->format('Y-m')) {
            $this->googleCalendar->syncUser($request->user());
        }
        $view = in_array($request->query('view'), ['week', 'month'], true) ? $request->query('view') : 'week';
        $weekStart = $request->user()->week_starts_on ?? 1;
        $weekEnd = ($weekStart + 6) % 7;
        [$start, $end] = match ($view) {
            'month' => [$focus->copy()->startOfMonth()->startOfWeek($weekStart), $focus->copy()->endOfMonth()->endOfWeek($weekEnd)],
            default => [$focus->copy()->startOfWeek($weekStart), $focus->copy()->endOfWeek($weekEnd)],
        };
        $todayVisible = now()->startOfDay()->betweenIncluded($start, $end);
        $logs = DailyLog::where('user_id', $request->user()->id)
            ->whereBetween('log_date', [$start, $end])->withCount('blocks')->get()->keyBy(fn ($log) => $log->log_date->toDateString());
        $days = collect();
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days->push($day->copy());
        }

        if (! $request->user()->is_guest) {
            $this->goalProgress->syncForUser($request->user());
        }
        $goalSnapshots = $request->user()->goals()->with('entries')->get()
            ->filter(fn ($goal) => $goal->isAvailableOn($focus))
            ->map(fn ($goal) => $this->goalProgress->snapshot($goal, $focus, $weekStart))
            ->sortBy(fn ($snapshot) => $snapshot['complete'] ? 1 : 0)->values();

        return view('calendar.index', compact('focus', 'view', 'start', 'end', 'todayVisible', 'logs', 'days', 'goalSnapshots'));
    }
}
