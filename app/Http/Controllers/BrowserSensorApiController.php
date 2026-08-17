<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\BrowsingActivityRecorder;
use Illuminate\Http\Request;

class BrowserSensorApiController extends Controller
{
    public function __invoke(Request $request, BrowsingActivityRecorder $recorder)
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'observed_at' => ['nullable', 'date'],
            'client_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $key = (string) $request->header('X-CaptainsLog-Key');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{32,128}$/', $key), 401, 'Browser sensor key required.');
        $sensor = Sensor::with('user')
            ->where('type', Sensor::BROWSER)
            ->where('enabled', true)
            ->where('pairing_key_hash', hash('sha256', $key))
            ->first();
        abort_unless($sensor, 401, 'Browser sensor is not paired.');

        $activity = $recorder->record($sensor, $data['url'], $data['client_id'], $data['observed_at'] ?? null);

        return response()->json([
            'message' => 'Browsing activity recorded.',
            'domain' => $activity->domain,
            'log_date' => $activity->dailyLog->log_date->toDateString(),
            'started_at' => $activity->started_at->toIso8601String(),
        ], 201);
    }
}
