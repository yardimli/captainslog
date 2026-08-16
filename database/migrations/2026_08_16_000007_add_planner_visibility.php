<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_blocks', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('occurred_at');
            $table->index(['daily_log_id', 'is_hidden']);
        });

        Schema::create('hidden_planner_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_definition_id')->constrained()->cascadeOnDelete();
            $table->string('scheduled_time', 5);
            $table->timestamps();
            $table->unique(['daily_log_id', 'task_definition_id', 'scheduled_time'], 'hidden_planner_event_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hidden_planner_events');
        Schema::table('log_blocks', function (Blueprint $table) {
            $table->dropIndex(['daily_log_id', 'is_hidden']);
            $table->dropColumn('is_hidden');
        });
    }
};
