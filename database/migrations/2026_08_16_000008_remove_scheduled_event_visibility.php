<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('hidden_planner_events');
    }

    public function down(): void
    {
        Schema::create('hidden_planner_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_definition_id')->constrained()->cascadeOnDelete();
            $table->string('scheduled_time', 5);
            $table->timestamps();
            $table->unique(['daily_log_id', 'task_definition_id', 'scheduled_time'], 'hidden_planner_event_slot_unique');
        });
    }
};
