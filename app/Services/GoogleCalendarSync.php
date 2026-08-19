<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\GoogleCalendarEvent;
use App\Models\LogBlock;
use App\Models\Sensor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarSync
{
    public function __construct(private GoogleCalendarClient $google) {}

    public function syncUser(User $user, bool $force = false): bool
    {
        $sensor = $user->sensors()->where('type', Sensor::GOOGLE_CALENDAR)->where('enabled', true)->first();

        return $sensor ? $this->syncSensor($sensor, $force) : false;
    }

    public function syncSensor(Sensor $sensor, bool $force = false): bool
    {
        if (! $sensor->enabled || blank($sensor->token)) {
            return false;
        }
        if (! $force && $sensor->last_checked_at?->greaterThan(now()->subMinutes(15))) {
            return false;
        }

        $start = now()->startOfMonth();
        $end = $start->copy()->addMonth();
        $calendarId = data_get($sensor->settings, 'calendar_id', 'primary');
        try {
            $accessToken = $this->google->refreshAccessToken($sensor->token);
            $events = $this->google->events($accessToken, $start, $end, $calendarId);
            DB::transaction(function () use ($sensor, $events, $start, $end, $calendarId) {
                $activeKeys = collect();
                foreach ($events as $googleEvent) {
                    if (($googleEvent['status'] ?? null) === 'cancelled') {
                        continue;
                    }
                    $normalized = $this->normalize($googleEvent);
                    if (! $normalized) {
                        continue;
                    }
                    $activeKeys->push($normalized['event_key']);
                    $this->storeEvent($sensor, $calendarId, $normalized);
                }

                $stale = GoogleCalendarEvent::with('logBlock')
                    ->where('sensor_id', $sensor->id)
                    ->where('starts_at', '>=', $start)
                    ->where('starts_at', '<', $end)
                    ->when($activeKeys->isNotEmpty(), fn ($query) => $query->whereNotIn('event_key', $activeKeys->unique()->all()))
                    ->get();
                foreach ($stale as $event) {
                    $block = $event->logBlock;
                    $event->delete();
                    $block?->delete();
                }
                $sensor->update([
                    'last_checked_at' => now(),
                    'last_error' => null,
                    'settings' => array_merge($sensor->settings ?? [], ['calendar_id' => $calendarId, 'last_event_count' => $activeKeys->unique()->count(), 'synced_month' => $start->format('Y-m')]),
                ]);
            });

            return true;
        } catch (Throwable $error) {
            $sensor->update(['last_checked_at' => now(), 'last_error' => $error->getMessage()]);
            Log::warning('Google Calendar sensor sync failed.', ['sensor_id' => $sensor->id, 'message' => $error->getMessage()]);

            return false;
        }
    }

    private function normalize(array $event): ?array
    {
        $dateTime = data_get($event, 'start.dateTime');
        $date = data_get($event, 'start.date');
        if (! $dateTime && ! $date) {
            return null;
        }
        $allDay = ! $dateTime;
        $startsAt = $allDay
            ? Carbon::createFromFormat('Y-m-d', $date, config('app.timezone'))->startOfDay()
            : Carbon::parse($dateTime)->setTimezone(config('app.timezone'));
        $endDateTime = data_get($event, 'end.dateTime');
        $endDate = data_get($event, 'end.date');
        $endsAt = $endDateTime ? Carbon::parse($endDateTime)->setTimezone(config('app.timezone'))
            : ($endDate ? Carbon::createFromFormat('Y-m-d', $endDate, config('app.timezone'))->startOfDay() : null);

        return [
            'google_event_id' => mb_substr((string) $event['id'], 0, 1024),
            'event_key' => hash('sha256', (string) $event['id']),
            'title' => mb_substr(trim(strip_tags((string) ($event['summary'] ?? 'Untitled calendar event'))) ?: 'Untitled calendar event', 0, 500),
            'description' => filled($event['description'] ?? null) ? trim(strip_tags((string) $event['description'])) : null,
            'location' => filled($event['location'] ?? null) ? mb_substr(trim(strip_tags((string) $event['location'])), 0, 500) : null,
            'html_link' => filter_var($event['htmlLink'] ?? null, FILTER_VALIDATE_URL) ? mb_substr($event['htmlLink'], 0, 2048) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => $allDay,
            'etag' => isset($event['etag']) ? mb_substr((string) $event['etag'], 0, 255) : null,
        ];
    }

    private function storeEvent(Sensor $sensor, string $calendarId, array $event): void
    {
        $log = DailyLog::where('user_id', $sensor->user_id)->whereDate('log_date', $event['starts_at'])->first()
            ?? DailyLog::create(['user_id' => $sensor->user_id, 'log_date' => $event['starts_at']->copy()->startOfDay()]);
        $record = GoogleCalendarEvent::with('logBlock')->where('sensor_id', $sensor->id)->where('event_key', $event['event_key'])->first();
        $block = $record?->logBlock;
        if (! $block) {
            $block = $log->blocks()->create([
                'type' => 'sensor_google_calendar',
                'emoji' => '📅',
                'content' => $event['title'],
                'position' => ((int) $log->blocks()->max('position')) + 1,
                'occurred_at' => $event['starts_at'],
            ]);
        } else {
            $block->daily_log_id = $log->id;
        }
        $block->fill([
            'content' => $event['title'],
            'occurred_at' => $event['starts_at'],
            'metadata' => [
                'sensor' => Sensor::GOOGLE_CALENDAR,
                'google_event_id' => $event['google_event_id'],
                'description' => $event['description'],
                'location' => $event['location'],
                'html_link' => $event['html_link'],
                'ends_at' => $event['ends_at']?->toIso8601String(),
                'is_all_day' => $event['is_all_day'],
            ],
        ])->save();

        GoogleCalendarEvent::updateOrCreate(
            ['sensor_id' => $sensor->id, 'event_key' => $event['event_key']],
            ['user_id' => $sensor->user_id, 'daily_log_id' => $log->id, 'log_block_id' => $block->id, 'calendar_id' => $calendarId, ...$event]
        );
    }
}
