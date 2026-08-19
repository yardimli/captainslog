<?php

namespace App\Http\Controllers;

use App\Models\Sensor;
use App\Services\GoogleCalendarClient;
use App\Services\GoogleCalendarSync;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleCalendarSensorController extends Controller
{
    public function __construct(private GoogleCalendarClient $google, private GoogleCalendarSync $sync) {}

    public function connect(Request $request)
    {
        abort_unless($this->google->configured(), 503, 'Google Calendar OAuth is not configured.');
        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($this->google->authorizationUrl($state, route('sensors.google-calendar.callback')));
    }

    public function callback(Request $request)
    {
        $expectedState = (string) $request->session()->pull('google_calendar_oauth_state');
        abort_unless($expectedState !== '' && hash_equals($expectedState, (string) $request->query('state')), 419, 'Google Calendar authorization state expired.');
        if ($request->filled('error')) {
            return redirect()->route('sensors.index')->with('error', 'Google Calendar access was not approved.');
        }
        $request->validate(['code' => ['required', 'string', 'max:4096']]);

        try {
            $tokens = $this->google->exchangeCode($request->query('code'), route('sensors.google-calendar.callback'));
            $refreshToken = $tokens['refresh_token'] ?? null;
            if (! is_string($refreshToken) || $refreshToken === '') {
                throw new RuntimeException('Google did not return an offline refresh token. Reconnect and approve access again.');
            }
            $account = $this->google->account($tokens['access_token']);
            $sensor = $request->user()->sensors()->updateOrCreate(
                ['type' => Sensor::GOOGLE_CALENDAR],
                [
                    'username' => $account['email'],
                    'token' => $refreshToken,
                    'enabled' => true,
                    'settings' => ['calendar_id' => 'primary', 'google_sub' => $account['sub'] ?? null],
                    'last_checked_at' => null,
                    'last_error' => null,
                ]
            );
            $this->sync->syncSensor($sensor, true);
        } catch (RuntimeException $error) {
            return redirect()->route('sensors.index')->with('error', $error->getMessage());
        }

        return redirect()->route('sensors.index')->with('status', 'Google Calendar linked and this month was synced.');
    }

    public function toggle(Request $request)
    {
        $sensor = $this->sensor($request);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $sensor->update(['enabled' => (bool) $data['enabled']]);
        if ($sensor->enabled) {
            $this->sync->syncSensor($sensor, true);
        }
        $message = $sensor->enabled ? 'Google Calendar sensor enabled.' : 'Google Calendar sensor disabled.';

        return $request->expectsJson() ? response()->json(['message' => $message, 'reload' => true]) : back()->with('status', $message);
    }

    public function sync(Request $request)
    {
        $sensor = $this->sensor($request);
        abort_unless($sensor->enabled, 422, 'Enable Google Calendar before syncing.');
        $ok = $this->sync->syncSensor($sensor, true);
        $message = $ok ? 'Google Calendar synced for the current month.' : ($sensor->fresh()->last_error ?: 'Google Calendar did not sync.');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'reload' => true], $ok ? 200 : 422)
            : back()->with($ok ? 'status' : 'error', $message);
    }

    public function unlink(Request $request)
    {
        $this->sensor($request)->delete();
        $message = 'Google Calendar unlinked. Existing calendar log entries were preserved.';

        return $request->expectsJson() ? response()->json(['message' => $message, 'reload' => true]) : back()->with('status', $message);
    }

    private function sensor(Request $request): Sensor
    {
        return $request->user()->sensors()->where('type', Sensor::GOOGLE_CALENDAR)->firstOrFail();
    }
}
