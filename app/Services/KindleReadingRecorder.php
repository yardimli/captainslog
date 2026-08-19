<?php

namespace App\Services;

use App\Models\DailyLog;
use App\Models\KindleReadingProgress;
use App\Models\LogBlock;
use App\Models\Sensor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KindleReadingRecorder
{
    public function record(Sensor $sensor, array $data): KindleReadingProgress
    {
        $observed = filled($data['observed_at'] ?? null) ? Carbon::parse($data['observed_at']) : now();
        $observed->setTimezone(config('app.timezone'));
        if ($observed->diffInMinutes(now(), true) > 10) {
            throw ValidationException::withMessages(['observed_at' => 'The Kindle timestamp must be within ten minutes of the server time.']);
        }

        $title = trim(strip_tags($data['title']));
        $asin = filled($data['asin'] ?? null) ? strtoupper($data['asin']) : null;
        $bookKey = $asin ?: hash('sha256', mb_strtolower($title));

        return DB::transaction(function () use ($sensor, $data, $observed, $title, $asin, $bookKey) {
            $log = DailyLog::where('user_id', $sensor->user_id)->whereDate('log_date', $observed)->first()
                ?? DailyLog::create(['user_id' => $sensor->user_id, 'log_date' => $observed->copy()->startOfDay()]);
            $latest = KindleReadingProgress::where('user_id', $sensor->user_id)
                ->where('book_key', $bookKey)
                ->latest('observed_at')
                ->lockForUpdate()
                ->first();

            if ($latest && $latest->observed_at->equalTo($observed)
                && (string) $latest->percentage_read === $this->percentage($data['percentage_read'] ?? null)
                && $latest->location === ($data['location'] ?? null)) {
                return $latest;
            }

            $block = KindleReadingProgress::where('daily_log_id', $log->id)
                ->where('book_key', $bookKey)
                ->with('logBlock')
                ->first()?->logBlock;
            if (! $block) {
                $block = $log->blocks()->create([
                    'type' => 'sensor_kindle',
                    'emoji' => '📖',
                    'content' => $title,
                    'position' => ((int) $log->blocks()->max('position')) + 1,
                    'occurred_at' => $observed,
                    'metadata' => ['sensor' => 'kindle', 'book_key' => $bookKey],
                ]);
            }

            $progress = KindleReadingProgress::create([
                'user_id' => $sensor->user_id,
                'daily_log_id' => $log->id,
                'log_block_id' => $block->id,
                'book_key' => $bookKey,
                'asin' => $asin,
                'title' => $title,
                'author' => filled($data['author'] ?? null) ? trim(strip_tags($data['author'])) : null,
                'percentage_read' => $data['percentage_read'] ?? null,
                'location' => filled($data['location'] ?? null) ? trim(strip_tags($data['location'])) : null,
                'client_id' => $data['client_id'],
                'observed_at' => $observed,
            ]);

            $readings = $block->kindleReadingProgress()->orderBy('observed_at')->get();
            $current = $readings->last();
            $firstPercentage = $readings->pluck('percentage_read')->filter(fn ($value) => $value !== null)->first();
            $currentPercentage = $current?->percentage_read;
            $content = $title;
            if ($currentPercentage !== null) {
                $content .= ' · '.rtrim(rtrim(number_format((float) $currentPercentage, 2), '0'), '.').'% read';
            } elseif (filled($current?->location)) {
                $content .= ' · '.$current->location;
            }
            $block->update([
                'content' => $content,
                'occurred_at' => $observed,
                'metadata' => array_merge($block->metadata ?? [], [
                    'title' => $title,
                    'author' => $current?->author,
                    'asin' => $asin,
                    'percentage_read' => $currentPercentage,
                    'started_at_percentage' => $firstPercentage,
                    'reading_count' => $readings->count(),
                ]),
            ]);
            $sensor->update(['last_checked_at' => now(), 'last_error' => null]);

            return $progress->fresh(['dailyLog']);
        });
    }

    private function percentage(mixed $value): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, 2, '.', '');
    }
}
