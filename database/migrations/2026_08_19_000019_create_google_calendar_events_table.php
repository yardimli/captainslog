<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('calendar_id', 255)->default('primary');
            $table->string('google_event_id', 1024);
            $table->char('event_key', 64);
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('html_link', 2048)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_all_day')->default(false);
            $table->string('etag', 255)->nullable();
            $table->timestamps();

            $table->unique(['sensor_id', 'event_key'], 'google_calendar_sensor_event_unique');
            $table->index(['user_id', 'starts_at']);
            $table->index(['daily_log_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_events');
    }
};
