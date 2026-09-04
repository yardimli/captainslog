<?php

namespace App\Http\Controllers;

use App\Models\LogBlock;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LogSearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:200']);
        $keyword = trim($data['q'] ?? '');
        $results = null;

        if (mb_strlen($keyword) >= 2) {
            $like = '%'.$keyword.'%';
            $results = LogBlock::query()
                ->whereHas('dailyLog', fn ($query) => $query->where('user_id', $request->user()->id))
                ->where(function ($query) use ($like) {
                    $query->where('content', 'like', $like)
                        ->orWhere('metadata', 'like', $like)
                        ->orWhereHas('taskEvent', fn ($event) => $event->where('task_name', 'like', $like)->orWhere('selected_value', 'like', $like))
                        ->orWhereHas('attachments', fn ($attachment) => $attachment->where('original_name', 'like', $like))
                        ->orWhereHas('browsingActivities', fn ($activity) => $activity->where('domain', 'like', $like))
                        ->orWhereHas('desktopActivities', fn ($activity) => $activity->where('application', 'like', $like))
                        ->orWhereHas('mobileBrowsingVisits', fn ($visit) => $visit->where('domain', 'like', $like));
                })
                ->with([
                    'dailyLog',
                    'taskEvent',
                    'attachments',
                    'browsingActivities',
                    'desktopActivities',
                    'mobileBrowsingVisits',
                ])
                ->orderByDesc('occurred_at')->orderByDesc('created_at')
                ->paginate(30)->withQueryString();

            $results->getCollection()->each(function (LogBlock $block) use ($request) {
                $block->setAttribute('search_details', $this->searchDetails($block, $request));
            });
        }

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('search.partials.results', compact('keyword', 'results'))->render(),
                'url' => $keyword === '' ? route('search.index') : route('search.index', ['q' => $keyword]),
            ]);
        }

        return view('search.index', compact('keyword', 'results'));
    }

    private function searchDetails(LogBlock $block, Request $request): ?array
    {
        if (in_array($block->type, ['sensor_browser', 'sensor_desktop', 'sensor_mobile_browser'], true)) {
            $mode = match ($block->type) {
                'sensor_desktop' => 'applications',
                'sensor_mobile_browser' => 'visits',
                default => 'duration',
            };
            $items = match ($mode) {
                'applications' => $block->desktopActivities->groupBy('application')
                    ->map(fn ($activities, $application) => ['domain' => $application, 'seconds' => (int) $activities->sum('duration_seconds')]),
                'visits' => $block->mobileBrowsingVisits->groupBy('domain')
                    ->map(fn ($visits, $domain) => ['domain' => $domain, 'visits' => $visits->count()]),
                default => $block->browsingActivities->groupBy('domain')
                    ->map(fn ($activities, $domain) => ['domain' => $domain, 'seconds' => (int) $activities->sum('duration_seconds')]),
            };
            $metric = $mode === 'visits' ? 'visits' : 'seconds';
            $items = $items->sortByDesc($metric)->values();

            return [
                'kind' => 'browsing',
                'mode' => $mode,
                'items' => $items->all(),
                'total' => (int) $items->sum($metric),
            ];
        }

        if ($block->type === 'sensor_github' && filled(data_get($block->metadata, 'commits'))) {
            return [
                'kind' => 'github',
                'project' => $block->content,
                'events' => collect(data_get($block->metadata, 'commits', []))->map(fn ($commit) => [
                    'time' => filled($commit['occurred_at'] ?? null) ? $request->user()->formatTime(Carbon::parse($commit['occurred_at'])) : '',
                    'sha' => $commit['sha'] ?? '',
                    'message' => $commit['message'] ?? null,
                    'url' => $commit['url'] ?? null,
                ])->values()->all(),
            ];
        }

        if ($block->type === 'sensor_google_calendar') {
            return [
                'kind' => 'calendar',
                'title' => $block->content,
                'start' => data_get($block->metadata, 'is_all_day') ? 'All day' : $request->user()->formatTime($block->occurred_at),
                'end' => data_get($block->metadata, 'ends_at') ? $request->user()->formatTime(Carbon::parse(data_get($block->metadata, 'ends_at'))) : null,
                'description' => data_get($block->metadata, 'description'),
                'location' => data_get($block->metadata, 'location'),
                'url' => data_get($block->metadata, 'html_link'),
            ];
        }

        return null;
    }
}
