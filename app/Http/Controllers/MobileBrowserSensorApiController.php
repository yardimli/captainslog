<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\MobileBrowsingRecorder;
use Illuminate\Http\Request;

class MobileBrowserSensorApiController extends Controller
{
    public function __invoke(Request $request, MobileBrowsingRecorder $recorder)
    {
        $data = $request->validate([
            'visits' => ['required', 'array', 'min:1', 'max:500'],
            'visits.*.url' => ['required', 'url:http,https', 'max:2048'],
            'visits.*.visited_at' => ['required', 'date'],
            'visits.*.visit_key' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $key = (string) $request->header('X-CaptainsLog-Key');
        abort_unless(preg_match('/^[A-Za-z0-9_-]{32,128}$/', $key), 401, 'Browser sensor key required.');
        $sensor = Sensor::with('user')
            ->where('type', Sensor::BROWSER)
            ->where('enabled', true)
            ->where('pairing_key_hash', hash('sha256', $key))
            ->first();
        abort_unless($sensor, 401, 'Chrome extension is not paired.');

        return response()->json($recorder->recordBatch($sensor, $data['visits']), 201);
    }
}
