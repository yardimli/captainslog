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
                'percentage_read' => null,
                'location' => null,
                'client_id' => $data['client_id'],
                'observed_at' => $observed,
            ]);

            $manualLogCount = $block->kindleReadingProgress()->count();
            $block->update([
                'content' => $title,
                'occurred_at' => $observed,
                'metadata' => array_merge($block->metadata ?? [], [
                    'title' => $title,
                    'author' => $progress->author,
                    'asin' => $asin,
                    'manual_log_count' => $manualLogCount,
                ]),
            ]);
            $sensor->update(['last_checked_at' => now(), 'last_error' => null]);

            return $progress->fresh(['dailyLog']);
        });
    }

}
