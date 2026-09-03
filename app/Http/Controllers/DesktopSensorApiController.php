<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\DesktopActivityRecorder;
use Illuminate\Http\Request;

class DesktopSensorApiController extends Controller
{
    public function __invoke(Request $request, DesktopActivityRecorder $recorder)
    {
        $data = $request->validate([
            'application' => ['required', 'string', 'max:255'],
            'process_name' => ['required', 'string', 'max:255', 'regex:/^[^\\\\\/]+$/'],
            'observed_at' => ['nullable', 'date'],
            'client_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        $key = (string) $request->header('X-TotalLog-Key');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{32,128}$/', $key), 401, 'Desktop sensor key required.');
        $sensor = Sensor::with('user')
            ->where('type', Sensor::DESKTOP)
            ->where('enabled', true)
            ->where('pairing_key_hash', hash('sha256', $key))
            ->first();
        abort_unless($sensor, 401, 'Desktop sensor is not paired.');

        $activity = $recorder->record($sensor, $data);

        return response()->json([
            'message' => 'Desktop activity recorded.',
            'application' => $activity->application,
            'log_date' => $activity->dailyLog->log_date->toDateString(),
            'started_at' => $activity->started_at->toIso8601String(),
        ], 201);
    }
}
