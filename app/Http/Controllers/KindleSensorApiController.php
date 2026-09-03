<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\KindleReadingRecorder;
use Illuminate\Http\Request;

class KindleSensorApiController extends Controller
{
    public function __invoke(Request $request, KindleReadingRecorder $recorder)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'author' => ['nullable', 'string', 'max:500'],
            'asin' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
            'percentage_read' => ['nullable', 'numeric', 'between:0,100'],
            'location' => ['nullable', 'string', 'max:100'],
            'observed_at' => ['nullable', 'date'],
            'client_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);
        abort_if(! array_key_exists('percentage_read', $data) && blank($data['location'] ?? null), 422, 'A Kindle percentage or location is required.');

        $key = (string) $request->header('X-TotalLog-Key');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{32,128}$/', $key), 401, 'Browser sensor key required.');
        $sensor = Sensor::with('user')
            ->where('type', Sensor::BROWSER)
            ->where('enabled', true)
            ->where('pairing_key_hash', hash('sha256', $key))
            ->first();
        abort_unless($sensor, 401, 'Chrome extension is not paired.');

        $progress = $recorder->record($sensor, $data);

        return response()->json([
            'message' => 'Kindle reading progress recorded.',
            'title' => $progress->title,
            'percentage_read' => $progress->percentage_read === null ? null : (float) $progress->percentage_read,
            'log_date' => $progress->dailyLog->log_date->toDateString(),
        ], 201);
    }
}
