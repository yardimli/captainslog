<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('emoji', 32)->default('🎯');
            $table->string('color', 7)->default('#4f46e5');
            $table->unsignedInteger('target_points');
            $table->string('period', 16)->default('weekly');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('manual_enabled')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'start_date', 'end_date']);
        });

        Schema::create('goal_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->foreignId('task_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('github_project')->nullable();
            $table->timestamps();
            $table->unique(['goal_id', 'task_definition_id']);
            $table->unique(['goal_id', 'github_project']);
        });

        Schema::create('goal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_source_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->unsignedInteger('points')->default(1);
            $table->string('external_key')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['goal_id', 'external_key']);
            $table->index(['goal_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_entries');
        Schema::dropIfExists('goal_sources');
        Schema::dropIfExists('goals');
    }
};
