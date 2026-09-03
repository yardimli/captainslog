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
        abort_unless($sensor, 401, 'Desktop app is not paired.');

        $book = $recorder->record($sensor, $data);

        return response()->json([
            'message' => 'Kindle book logged.',
            'title' => $book->title,
            'log_date' => $book->dailyLog->log_date->toDateString(),
        ], 201);
    }
}
