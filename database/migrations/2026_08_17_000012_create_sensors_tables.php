<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('username', 80)->nullable();
            $table->text('token')->nullable();
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'type']);
            $table->index(['user_id', 'enabled']);
        });

        Schema::create('sensor_day_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->string('status', 24)->default('complete');
            $table->unsignedInteger('item_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['sensor_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_day_syncs');
        Schema::dropIfExists('sensors');
    }
};
