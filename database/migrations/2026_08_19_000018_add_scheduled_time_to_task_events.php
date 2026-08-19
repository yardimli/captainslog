<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->string('scheduled_time', 5)->nullable()->after('selected_value');
            $table->index(['daily_log_id', 'task_definition_id', 'scheduled_time'], 'task_events_daily_task_slot_index');
        });

        DB::table('task_events')
            ->whereNotNull('task_definition_id')
            ->orderBy('id')
            ->chunkById(200, function ($events) {
                $definitions = DB::table('task_definitions')
                    ->whereIn('id', $events->pluck('task_definition_id')->filter()->unique())
                    ->pluck('scheduled_times', 'id');

                foreach ($events as $event) {
                    $times = json_decode($definitions[$event->task_definition_id] ?? '[]', true) ?: [];
                    if (! $times) {
                        continue;
                    }
                    $eventMinute = ((int) substr($event->occurred_at, 11, 2) * 60) + (int) substr($event->occurred_at, 14, 2);
                    $nearest = collect($times)->sortBy(function ($time) use ($eventMinute) {
                        [$hour, $minute] = array_map('intval', explode(':', $time));

                        return abs((($hour * 60) + $minute) - $eventMinute);
                    })->first();
                    DB::table('task_events')->where('id', $event->id)->update(['scheduled_time' => $nearest]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->dropIndex('task_events_daily_task_slot_index');
            $table->dropColumn('scheduled_time');
        });
    }
};
