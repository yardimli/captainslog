<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->constrained()->cascadeOnDelete();
            $table->string('application');
            $table->string('process_name');
            $table->string('client_id', 64);
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['daily_log_id', 'application']);
            $table->index(['user_id', 'client_id', 'ended_at'], 'desktop_user_client_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_activities');
    }
};
