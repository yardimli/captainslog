<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GuestDemoService
{
    public const COOKIE = 'totallog_guest';

    private const SEED_VERSION = 3;

    public function account(Request $request): User
    {
        if ($request->attributes->has('guest_demo_user')) {
            return $request->attributes->get('guest_demo_user');
        }

        $token = $request->cookie(self::COOKIE);
        $validToken = is_string($token) && preg_match('/^[A-Za-z0-9]{64}$/', $token);
        $user = $validToken ? User::where('guest_token_hash', hash('sha256', $token))->where('is_guest', true)->first() : null;

        if (! $user) {
            $token = Str::random(64);
            $user = User::create([
                'name' => 'Guest User',
                'email' => 'guest-'.Str::lower(Str::random(24)).'@demo.invalid',
                'password' => Hash::make(Str::random(64)),
                'is_guest' => true,
                'guest_token_hash' => hash('sha256', $token),
            ]);
            Cookie::queue(Cookie::make(self::COOKIE, $token, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        $this->seedRollingWeek($user);
        $request->attributes->set('guest_demo_user', $user);

        return $user;
    }

    private function seedRollingWeek(User $user): void
    {
        DB::transaction(function () use ($user) {
            $tasks = $this->tasks($user);
            foreach (range(7, 0) as $daysAgo) {
                $date = today()->subDays($daysAgo);
                if (DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->exists()) {
                    continue;
                }
                $this->seedDay($user, $date, $daysAgo, $tasks);
            }
            if ($user->demo_seed_version < self::SEED_VERSION) {
                $this->upgradeDemo($user, $tasks);
            }
        });
    }

    private function tasks(User $user): array
    {
        $definitions = [
            ['name' => 'Medication', 'emoji' => '💊', 'color' => '#e11d48', 'is_sticky' => true, 'recurrence_type' => 'daily', 'scheduled_times' => ['08:00', '20:00'], 'options' => ['Morning dose', 'Evening dose']],
            ['name' => 'Dog walk', 'emoji' => '🐕', 'color' => '#059669', 'is_sticky' => true, 'recurrence_type' => 'daily', 'scheduled_times' => ['07:30', '18:30'], 'options' => ['Short walk', 'Park loop', 'Long walk']],
            ['name' => 'Language study', 'emoji' => '🗣️', 'color' => '#4f46e5', 'is_sticky' => false, 'recurrence_type' => 'daily', 'scheduled_times' => ['19:00'], 'options' => ['Vocabulary', 'Listening', 'Conversation']],
            ['name' => 'Groceries', 'emoji' => '🛒', 'color' => '#d97706', 'is_sticky' => false, 'recurrence_type' => 'weekly', 'recurrence_days' => [6], 'scheduled_times' => ['11:00'], 'options' => null],
            ['name' => 'Exercise', 'emoji' => '🚶', 'color' => '#0284c7', 'is_sticky' => false, 'recurrence_type' => 'weekly', 'recurrence_days' => [1, 3, 5], 'scheduled_times' => ['07:00'], 'options' => ['Walk', 'Stretching', 'Gym']],
        ];

        return collect($definitions)->mapWithKeys(function ($definition) use ($user) {
            $task = TaskDefinition::updateOrCreate(['user_id' => $user->id, 'name' => $definition['name']], $definition);

            return [$definition['name'] => $task];
        })->all();
    }

    private function seedDay(User $user, Carbon $date, int $daysAgo, array $tasks): void
    {
        $entries = $this->entries()[$daysAgo];
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => $date]);
        foreach ($entries as $position => $entry) {
            $occurredAt = $date->copy()->setTimeFromTimeString($entry['time'] ?? sprintf('%02d:15', 7 + $position));
            $log->blocks()->forceCreate([
                'type' => $entry['type'] ?? 'text',
                'emoji' => $entry['emoji'] ?? LogBlock::defaultEmojiForType($entry['type'] ?? 'text'),
                'content' => $entry['content'],
                'metadata' => ['demo' => true],
                'position' => $position + 1,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ]);
        }

        $medication = $log->blocks()->forceCreate(['type' => 'event', 'emoji' => $tasks['Medication']->emoji, 'metadata' => ['demo' => true], 'position' => 90, 'occurred_at' => $date->copy()->setTime(20, 5), 'created_at' => $date->copy()->setTime(20, 5), 'updated_at' => $date->copy()->setTime(20, 5)]);
        TaskEvent::create([
            'daily_log_id' => $log->id,
            'task_definition_id' => $tasks['Medication']->id,
            'log_block_id' => $medication->id,
            'task_name' => 'Medication',
            'selected_value' => 'Evening dose',
            'occurred_at' => $date->copy()->setTime(20, 5),
        ]);
    }

    private function upgradeDemo(User $user, array $tasks): void
    {
        TaskDefinition::where('user_id', $user->id)
            ->whereIn('name', ['Dog medication', 'Yoga class', 'Anger check', 'Pet care', 'Weigh-in'])
            ->update(['is_active' => false]);

        foreach (range(7, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);
            $log = DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->first();
            if (! $log) {
                continue;
            }
            foreach ($this->entries()[$daysAgo] as $position => $entry) {
                $block = $log->blocks()->where('position', $position + 1)->first();
                $occurredAt = $date->copy()->setTimeFromTimeString($entry['time'] ?? sprintf('%02d:15', 7 + $position));
                if ($block && data_get($block->metadata, 'demo')) {
                    $block->forceFill([
                        'type' => $entry['type'] ?? 'text',
                        'emoji' => $entry['emoji'] ?? LogBlock::defaultEmojiForType($entry['type'] ?? 'text'),
                        'content' => $entry['content'],
                        'occurred_at' => $occurredAt,
                    ])->save();
                } elseif (! $block) {
                    $log->blocks()->forceCreate([
                        'type' => $entry['type'] ?? 'text',
                        'emoji' => $entry['emoji'] ?? LogBlock::defaultEmojiForType($entry['type'] ?? 'text'),
                        'content' => $entry['content'],
                        'metadata' => ['demo' => true],
                        'position' => $position + 1,
                        'occurred_at' => $occurredAt,
                        'created_at' => $occurredAt,
                        'updated_at' => $occurredAt,
                    ]);
                }
            }
            $eventBlock = $log->blocks()->where('type', 'event')->first();
            if ($eventBlock && data_get($eventBlock->metadata, 'demo')) {
                $eventBlock->update(['emoji' => $tasks['Medication']->emoji, 'position' => 90]);
                $eventBlock->taskEvent?->update(['task_definition_id' => $tasks['Medication']->id, 'task_name' => 'Medication', 'selected_value' => 'Evening dose']);
            }
        }

        Attachment::where('user_id', $user->id)->whereIn('path', ['demo-yoga-observation-deck.png', 'demo-pet-medication.png'])->get()->each(function (Attachment $attachment) {
            $block = $attachment->logBlock;
            $attachment->delete();
            if ($block && data_get($block->metadata, 'demo')) {
                $block->delete();
            }
        });
        $this->ensureDemoImage($user, today(), 'demo-dog-walk.png', 'media', '📷', 'Morning walk through the neighborhood park before starting work.', 20, '07:45');
        $this->ensureDemoImage($user, today()->subDay(), 'demo-language-study.png', 'media', '📷', 'Thirty minutes of language study at the kitchen table.', 20, '18:40');
        $user->forceFill(['demo_seed_version' => self::SEED_VERSION])->save();
    }

    private function ensureDemoImage(User $user, Carbon $date, string $filename, string $type, string $emoji, string $content, int $position, string $time): void
    {
        $disk = Storage::disk('demo_assets');
        if (! $disk->exists($filename)) {
            return;
        }
        $log = DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->firstOrFail();
        $block = $log->blocks()->where('metadata->demo_asset', $filename)->first();
        if (! $block) {
            $block = $log->blocks()->create([
                'type' => $type,
                'emoji' => $emoji,
                'content' => $content,
                'metadata' => ['demo' => true, 'demo_asset' => $filename],
                'position' => $position,
                'occurred_at' => $date->copy()->setTimeFromTimeString($time),
            ]);
        }
        Attachment::firstOrCreate(
            ['user_id' => $user->id, 'daily_log_id' => $log->id, 'log_block_id' => $block->id, 'disk' => 'demo_assets', 'path' => $filename],
            ['type' => 'image', 'original_name' => $filename, 'mime_type' => 'image/png', 'size' => $disk->size($filename), 'metadata' => ['shared_demo_asset' => true]],
        );
    }

    private function entries(): array
    {
        return [
            7 => [
                ['time' => '07:20', 'emoji' => '🐕', 'content' => 'Took Milo around the park before breakfast.'],
                ['time' => '10:00', 'type' => 'sensor_google_calendar', 'content' => 'Calendar · Dentist appointment · 10:00–10:45 AM'],
                ['time' => '18:10', 'emoji' => '🛒', 'content' => 'Picked up vegetables, rice, coffee, and dog food.'],
            ],
            6 => [
                ['time' => '08:05', 'emoji' => '💊', 'content' => 'Took morning medication with breakfast.'],
                ['time' => '12:00', 'type' => 'sensor_desktop', 'content' => "Desktop activity · 3 apps · 1 hr 18 min\nVisual Studio Code 46m · Excel 21m · Slack 11m"],
                ['time' => '17:30', 'type' => 'sensor_browser', 'content' => "Desktop browsing · 34 min\nlanguagelearning.example 18m · recipes.example 9m · weather.example 7m"],
            ],
            5 => [
                ['time' => '07:40', 'emoji' => '🐕', 'content' => 'Short dog walk because it started raining.'],
                ['time' => '13:15', 'type' => 'sensor_kindle', 'content' => 'Kindle reading · The Little Prince · Antoine de Saint-Exupéry'],
                ['time' => '19:00', 'emoji' => '🗣️', 'content' => 'Reviewed twenty Japanese vocabulary cards and practiced listening for fifteen minutes.'],
            ],
            4 => [
                ['time' => '09:00', 'type' => 'sensor_google_calendar', 'content' => 'Calendar · Weekly team meeting · 9:00–9:30 AM'],
                ['time' => '11:30', 'emoji' => '🛒', 'content' => 'Grocery run and dropped off recycling on the way home.'],
                ['time' => '16:00', 'type' => 'sensor_mobile_browser', 'content' => "Mobile browsing · 8 visits\nnews.example 3 · maps.example 3 · bank.example 2"],
            ],
            3 => [
                ['time' => '08:00', 'emoji' => '💊', 'content' => 'Morning medication taken. Refilled the weekly pill organizer.'],
                ['time' => '14:00', 'type' => 'sensor_desktop', 'content' => "Desktop activity · 4 apps · 2 hr 6 min\nWord 52m · Chrome 44m · Spotify 18m · Files 12m"],
                ['time' => '18:35', 'emoji' => '🐕', 'content' => 'Long dog walk by the river.'],
            ],
            2 => [
                ['time' => '10:20', 'type' => 'sensor_browser', 'content' => "Desktop browsing · 27 min\ndocs.example 15m · email.example 8m · calendar.example 4m"],
                ['time' => '15:45', 'type' => 'sensor_kindle', 'content' => 'Kindle reading · The Little Prince · Antoine de Saint-Exupéry'],
                ['time' => '19:10', 'emoji' => '🗣️', 'content' => 'Language study: practiced ordering food and asking for directions.'],
            ],
            1 => [
                ['time' => '08:30', 'emoji' => '🍳', 'content' => 'Made oatmeal and prepared lunches for the next two days.'],
                ['time' => '12:30', 'type' => 'sensor_mobile_browser', 'content' => "Mobile browsing · 11 visits\ntransit.example 5 · news.example 4 · weather.example 2"],
                ['time' => '18:30', 'type' => 'chat_assistant', 'content' => 'You completed your planned walk, language practice, and grocery trip this week.'],
            ],
            0 => [
                ['time' => '07:30', 'emoji' => '🐕', 'content' => 'Took Milo for a twenty-minute walk around the neighborhood.'],
                ['time' => '09:00', 'type' => 'sensor_google_calendar', 'content' => 'Calendar · Japanese lesson · 9:00–10:00 AM'],
                ['time' => '11:00', 'type' => 'sensor_desktop', 'content' => "Desktop activity · 3 apps · 1 hr 25 min\nVisual Studio Code 48m · Chrome 25m · Notes 12m"],
                ['time' => '12:30', 'type' => 'sensor_browser', 'content' => "Desktop browsing · 31 min\ndocs.example 14m · languagelearning.example 10m · email.example 7m"],
                ['time' => '14:10', 'type' => 'sensor_mobile_browser', 'content' => "Mobile browsing · 9 visits\nmaps.example 4 · groceries.example 3 · weather.example 2"],
                ['time' => '16:20', 'type' => 'sensor_kindle', 'content' => 'Kindle reading · The Little Prince · Antoine de Saint-Exupéry'],
                ['time' => '17:30', 'emoji' => '🛒', 'content' => 'Bought fruit, vegetables, milk, and dog treats.'],
                ['time' => '18:15', 'type' => 'chat_assistant', 'content' => 'Today includes focused work, language study, reading, errands, and time outside.'],
            ],
        ];
    }
}
