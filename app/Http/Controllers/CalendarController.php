<?php

namespace App\Http\Controllers;

use App\Models\DailyLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request, ?string $date = null)
    {
        $focus = rescue(fn () => Carbon::parse($date ?: now()), now(), false)->startOfDay();
        $view = in_array($request->query('view'), ['day', 'week', 'month'], true) ? $request->query('view') : 'week';
        [$start, $end] = match ($view) {
            'day' => [$focus->copy(), $focus->copy()],
            'month' => [$focus->copy()->startOfMonth()->startOfWeek(), $focus->copy()->endOfMonth()->endOfWeek()],
            default => [$focus->copy()->startOfWeek(), $focus->copy()->endOfWeek()],
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
