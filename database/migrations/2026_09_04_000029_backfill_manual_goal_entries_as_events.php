<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('goal_entries')
            ->whereNull('goal_source_id')
            ->whereNull('external_key')
            ->orderBy('id')
            ->eachById(function ($entry) {
                $goal = DB::table('goals')->find($entry->goal_id);
                if (! $goal) {
                    return;
                }

                $occurredAt = Carbon::parse($entry->occurred_at);
                $log = DB::table('daily_logs')
                    ->where('user_id', $goal->user_id)
                    ->whereDate('log_date', $occurredAt->toDateString())
                    ->first();
                $now = now();
                $logId = $log?->id ?? DB::table('daily_logs')->insertGetId([
                    'user_id' => $goal->user_id,
                    'log_date' => $occurredAt->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $blockId = DB::table('log_blocks')->insertGetId([
                    'daily_log_id' => $logId,
                    'type' => 'event',
                    'emoji' => $goal->emoji,
                    'content' => $entry->note,
                    'metadata' => json_encode(['goal_id' => $goal->id, 'goal_entry_id' => $entry->id]),
                    'position' => ($occurredAt->hour * 3600) + ($occurredAt->minute * 60) + $occurredAt->second,
                    'occurred_at' => $occurredAt,
                    'is_hidden' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('task_events')->insert([
                    'daily_log_id' => $logId,
                    'task_definition_id' => null,
                    'log_block_id' => $blockId,
                    'task_name' => $goal->name,
                    'selected_value' => '+'.$entry->points.' '.((int) $entry->points === 1 ? 'point' : 'points'),
                    'scheduled_time' => null,
                    'occurred_at' => $occurredAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        DB::table('log_blocks')->where('type', 'event')->orderBy('id')->eachById(function ($block) {
            $metadata = json_decode($block->metadata ?? '', true);
            if (! is_array($metadata) || empty($metadata['goal_entry_id'])) {
                return;
            }

            DB::table('task_events')->where('log_block_id', $block->id)->delete();
            DB::table('log_blocks')->where('id', $block->id)->delete();
        });
    }
};
