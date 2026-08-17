<?php

namespace App\Services;

use App\Models\BrowsingActivity;
use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\Sensor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BrowsingActivityRecorder
{
    public const INACTIVITY_SECONDS = 180;

    public function record(Sensor $sensor, string $url, string $clientId, ?string $observedAt = null): BrowsingActivity
    {
        $domain = $this->registrableDomain($url);
        $observed = filled($observedAt) ? Carbon::parse($observedAt) : now();
        $observed->setTimezone(config('app.timezone'));
        if ($observed->diffInSeconds(now(), true) > 300) {
            throw ValidationException::withMessages(['observed_at' => 'The browsing timestamp must be within five minutes of the server time.']);
        }

        return DB::transaction(function () use ($sensor, $domain, $clientId, $observed) {
            $this->finalizeStale($sensor->user, $observed);
            $active = BrowsingActivity::where('user_id', $sensor->user_id)
                ->where('client_id', $clientId)
                ->whereNull('ended_at')
                ->latest('last_seen_at')
                ->lockForUpdate()
                ->first();
            $hourStart = $observed->copy()->startOfHour();
            $sameHour = $active
                && $active->daily_log_id === $this->dailyLog($sensor->user, $observed)->id
                && $active->started_at->copy()->startOfHour()->equalTo($hourStart);
            $gap = $active ? $active->last_seen_at->diffInSeconds($observed, false) : null;

            if ($active && $gap >= 0 && $gap <= self::INACTIVITY_SECONDS && $sameHour && $active->domain === $domain) {
                $active->update([
                    'last_seen_at' => $observed,
                    'duration_seconds' => max($active->duration_seconds, $active->started_at->diffInSeconds($observed)),
                ]);
                $this->refreshBlock($active->logBlock);
                $sensor->update(['last_checked_at' => now(), 'last_error' => null]);

                return $active->fresh();
            }

            if ($active) {
                $endedAt = $gap !== null && $gap > self::INACTIVITY_SECONDS
                    ? $active->last_seen_at
                    : ($sameHour ? $observed : $hourStart);
                $this->finish($active, $endedAt);
            }

            $log = $this->dailyLog($sensor->user, $observed);
            $block = $this->hourBlock($log, $hourStart, $observed);
            $activity = BrowsingActivity::create([
                'user_id' => $sensor->user_id,
                'daily_log_id' => $log->id,
                'log_block_id' => $block->id,
                'domain' => $domain,
                'client_id' => $clientId,
                'started_at' => $observed,
                'last_seen_at' => $observed,
                'duration_seconds' => 0,
            ]);
            $this->refreshBlock($block);
            $sensor->update(['last_checked_at' => now(), 'last_error' => null]);

            return $activity;
        });
    }

    public function finalizeStale(User $user, ?Carbon $at = null): void
    {
        $at ??= now();
        $stale = BrowsingActivity::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->where('last_seen_at', '<=', $at->copy()->subSeconds(self::INACTIVITY_SECONDS))
            ->lockForUpdate()
            ->get();

        foreach ($stale as $activity) {
            $this->finish($activity, $activity->last_seen_at);
        }
    }

    private function finish(BrowsingActivity $activity, Carbon $endedAt): void
    {
        if ($endedAt->lessThan($activity->started_at)) {
            $endedAt = $activity->last_seen_at;
        }
        $activity->update([
            'last_seen_at' => $endedAt->greaterThan($activity->last_seen_at) ? $endedAt : $activity->last_seen_at,
            'ended_at' => $endedAt,
            'duration_seconds' => max($activity->duration_seconds, $activity->started_at->diffInSeconds($endedAt)),
        ]);
        $this->refreshBlock($activity->logBlock);
    }

    private function dailyLog(User $user, Carbon $at): DailyLog
    {
        return DailyLog::where('user_id', $user->id)->whereDate('log_date', $at)->first()
            ?? DailyLog::create(['user_id' => $user->id, 'log_date' => $at->copy()->startOfDay()]);
    }

    private function hourBlock(DailyLog $log, Carbon $hourStart, Carbon $observed): LogBlock
    {
        $existing = BrowsingActivity::where('daily_log_id', $log->id)
            ->where('started_at', '>=', $hourStart)
            ->where('started_at', '<', $hourStart->copy()->addHour())
            ->with('logBlock')
            ->first()?->logBlock;
        if ($existing) {
            return $existing;
        }

        return $log->blocks()->create([
            'type' => 'sensor_browser',
            'emoji' => '🌐',
            'content' => 'Browsing · 1 domain · under 1 min',
            'position' => ((int) $log->blocks()->max('position')) + 1,
            'occurred_at' => $observed,
            'metadata' => [
                'sensor' => Sensor::BROWSER,
                'hour_start' => $hourStart->toIso8601String(),
                'total_seconds' => 0,
                'domain_count' => 1,
            ],
        ]);
    }

    private function refreshBlock(LogBlock $block): void
    {
        $activities = $block->browsingActivities()->get();
        $seconds = (int) $activities->sum('duration_seconds');
        $domains = $activities->pluck('domain')->unique()->count();
        $block->update([
            'content' => 'Browsing · '.$domains.' '.($domains === 1 ? 'domain' : 'domains').' · '.$this->duration($seconds),
            'occurred_at' => $activities->min('started_at') ?? $block->occurred_at,
            'metadata' => array_merge($block->metadata ?? [], ['total_seconds' => $seconds, 'domain_count' => $domains]),
        ]);
    }

    private function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return 'under 1 min';
        }
        $minutes = (int) floor($seconds / 60);

        return $minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    private function registrableDomain(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = trim($host, '.');
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            throw ValidationException::withMessages(['url' => 'Send an HTTP or HTTPS URL with a domain name.']);
        }
        $labels = explode('.', preg_replace('/^www\./', '', $host));
        $publicSuffixPairs = ['co.uk', 'org.uk', 'ac.uk', 'com.au', 'net.au', 'org.au', 'co.jp', 'co.nz', 'com.br', 'com.tw'];
        $lastTwo = implode('.', array_slice($labels, -2));
        $take = count($labels) >= 3 && in_array($lastTwo, $publicSuffixPairs, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }
}
