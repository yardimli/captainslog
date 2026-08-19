<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\GoogleCalendarSync;

class CalendarController extends Controller
{
    public function __construct(private GoogleCalendarSync $googleCalendar) {}

    public function index(Request $request, ?string $date = null)
    {
        $focus = rescue(fn () => Carbon::parse($date ?: now()), now(), false)->startOfDay();
        if ($focus->format('Y-m') === now()->format('Y-m')) {
            $this->googleCalendar->syncUser($request->user());
        }
        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'week';
        $weekStart = $request->user()->week_starts_on ?? 1;
        $weekEnd = ($weekStart + 6) % 7;
        [$start, $end] = match ($view) {
            'day' => [$focus->copy(), $focus->copy()],
            'month' => [$focus->copy()->startOfMonth()->startOfWeek($weekStart), $focus->copy()->endOfMonth()->endOfWeek($weekEnd)],
            default => [$focus->copy()->startOfWeek($weekStart), $focus->copy()->endOfWeek($weekEnd)],
        };
        $logs = DailyLog::where('user_id', $request->user()->id)
            ->whereBetween('log_date', [$start, $end])->withCount(['blocks', 'attachments'])->get()->keyBy(fn ($log) => $log->log_date->toDateString());
        $days = collect();
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days->push($day->copy());
        }

        return view('calendar.index', compact('focus', 'view', 'start', 'end', 'logs', 'days'));
    }
}
