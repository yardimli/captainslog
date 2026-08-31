<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_browsing_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 253);
            $table->char('visit_key', 64);
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->unique(['user_id', 'visit_key']);
            $table->index(['daily_log_id', 'visited_at']);
            $table->index(['log_block_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_browsing_visits');
    }
};
