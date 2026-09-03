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

    private const SEED_VERSION = 2;

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
            ['name' => 'Dog medication', 'emoji' => '💊', 'color' => '#e11d48', 'is_sticky' => true, 'recurrence_type' => 'daily', 'scheduled_times' => ['08:00', '20:00'], 'options' => ['Worf', "T'Paw", 'Both dogs']],
            ['name' => 'Yoga class', 'emoji' => '🧘', 'color' => '#4f46e5', 'is_sticky' => true, 'recurrence_type' => 'weekly', 'recurrence_days' => [1, 3, 5, 6, 7], 'scheduled_times' => ['10:00'], 'options' => ['Gentle', 'Warp core', 'Hot nebula']],
            ['name' => 'Anger check', 'emoji' => '😌', 'color' => '#d97706', 'is_sticky' => false, 'recurrence_type' => 'daily', 'scheduled_times' => ['16:30'], 'options' => ['Calm', 'Red alert', 'Klingon opera']],
            ['name' => 'Pet care', 'emoji' => '🐾', 'color' => '#059669', 'is_sticky' => false, 'recurrence_type' => 'daily', 'scheduled_times' => ['07:30', '19:00'], 'options' => ['Fed', 'Walked', 'Negotiated']],
            ['name' => 'Weigh-in', 'emoji' => '⚖️', 'color' => '#0284c7', 'is_sticky' => false, 'recurrence_type' => 'weekly', 'recurrence_days' => [1, 4], 'scheduled_times' => ['07:00'], 'options' => null],
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
            $log->blocks()->forceCreate([
                'type' => $entry['type'] ?? 'text',
                'emoji' => $entry['emoji'] ?? LogBlock::defaultEmojiForType($entry['type'] ?? 'text'),
                'content' => $entry['content'],
                'metadata' => ['demo' => true],
                'position' => $position + 1,
                'created_at' => $date->copy()->setTime(7 + ($position * 5), 15),
                'updated_at' => $date->copy()->setTime(7 + ($position * 5), 15),
            ]);
        }

        $medication = $log->blocks()->forceCreate(['type' => 'event', 'emoji' => $tasks['Dog medication']->emoji, 'metadata' => ['demo' => true], 'position' => 10, 'created_at' => $date->copy()->setTime(20, 5), 'updated_at' => $date->copy()->setTime(20, 5)]);
        TaskEvent::create([
            'daily_log_id' => $log->id,
            'task_definition_id' => $tasks['Dog medication']->id,
            'log_block_id' => $medication->id,
            'task_name' => 'Dog medication',
            'selected_value' => 'Both dogs',
            'occurred_at' => $date->copy()->setTime(20, 5),
        ]);
    }

    private function upgradeDemo(User $user, array $tasks): void
    {
        foreach (range(7, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);
            $log = DailyLog::where('user_id', $user->id)->whereDate('log_date', $date)->first();
            if (! $log) {
                continue;
            }
            foreach ($this->entries()[$daysAgo] as $position => $entry) {
                $block = $log->blocks()->where('position', $position + 1)->first();
                if ($block && data_get($block->metadata, 'demo')) {
                    $block->update(['emoji' => $entry['emoji'] ?? LogBlock::defaultEmojiForType($entry['type'] ?? 'text')]);
                }
            }
            $log->blocks()->where('position', 10)->where('type', 'event')->update(['emoji' => $tasks['Dog medication']->emoji]);
        }

        $this->ensureDemoImage($user, today(), 'demo-yoga-observation-deck.png', 'generated_image', '🎨', 'Observation-deck yoga began peacefully. Worf interpreted downward dog as a direct order and occupied the mat.', 20, '10:15');
        $this->ensureDemoImage($user, today()->subDay(), 'demo-pet-medication.png', 'media', '🖼️', 'Evening pet-care briefing: both dogs accepted their medication. Spot the cat supervised dosing protocol and demanded command credit.', 20, '19:20');
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
                ['emoji' => '🚀', 'content' => 'Total record: launched the USS Inner Peace at 06:00. Taught twelve cadets downward-facing targ while my beagle Worf stole a foam block.'],
                ['emoji' => '😌', 'content' => "Anger-management session went well until a client described breathing exercises as 'suggestions.' I counted to ten in Klingon and called it professional development."],
                ['type' => 'chat_assistant', 'content' => 'Computer: Meal plan recommends vegetables that have not been replicated in the shape of a starship.'],
            ],
            6 => [
                ['content' => 'Morning weigh-in: the scale announced a temporal anomaly. I announced it needed recalibration. We have agreed to mediation.'],
                ['content' => "Walked Worf and T'Paw. Spot the cat supervised from a windowsill like an admiral with extremely judgmental whiskers."],
                ['content' => 'Hot-nebula yoga class achieved maximum warp perspiration. Nobody was assimilated, although Gary did join the class newsletter.'],
            ],
            5 => [
                ['content' => "Counselor's log: helped a client replace 'engage rage engines' with 'I feel frustrated.' A diplomatic breakthrough."],
                ['content' => 'Prepared a high-protein lunch. Quinoa remains the Tribble of the pantry: one cup somehow became seventeen.'],
                ['content' => "T'Paw detected the pill hidden in cheese at six meters. Tried peanut butter. Resistance was futile, eventually."],
            ],
            4 => [
                ['content' => 'Total log: sunrise yoga on the observation deck. Spot sat on the mat during savasana and claimed salvage rights.'],
                ['content' => 'Red-alert moment: delivery arrived without the low-calorie dressing. Used the STOP technique and only drafted one strongly worded subspace message.'],
                ['type' => 'chat_assistant', 'content' => 'Computer: Daily victory detected—stairs used instead of turbolift. Commendation pending.'],
            ],
            3 => [
                ['content' => "Worf's medication administered on schedule. He performed the traditional beagle maneuver: swallowing the cheese and returning the tablet."],
                ['content' => 'Led chair yoga for the senior officers. Total chair refused to participate but provided excellent lumbar support.'],
                ['content' => 'Dinner: one sensible bowl of soup and a completely classified number of bread rolls. The logs have been sealed by Starfleet Wellness.'],
            ],
            2 => [
                ['content' => 'Anger group practiced calm boundary setting. “Shields up” is apparently not an approved example, even when said gently.'],
                ['content' => "Pet report: Worf walked, T'Paw medicated, Spot fed. Spot filed a grievance alleging the bowl was 4% below regulation capacity."],
                ['content' => 'Warp-core flow class: 42 minutes. My leggings survived, morale is high, and the snack drawer remains under observation.'],
            ],
            1 => [
                ['content' => 'Total record: resisted a doughnut at staff briefing. It used advanced cloaking technology and reappeared beside my coffee.'],
                ['content' => 'Therapy note: reframed “I will launch him out an airlock” as “I need space.” Concise, accurate, billing-friendly.'],
                ['type' => 'chat_assistant', 'content' => 'Computer: Three pets accounted for. Two dogs medicated. One cat continues to reject the chain of command.'],
            ],
            0 => [
                ['emoji' => '🧘', 'content' => 'Total record, present day: began with sun salutations while Worf treated my spine as a docking platform.'],
                ['emoji' => '📅', 'content' => "Today's mission: teach yoga at 10:00, counsel anger without developing any, walk Worf and T'Paw, negotiate with Spot, and lose weight at impulse speed."],
                ['type' => 'chat_assistant', 'content' => 'Computer: Course plotted for a calmer day. Tea is hot, dog medication is scheduled, and emergency biscuits remain within treaty limits.'],
            ],
        ];
    }
}
