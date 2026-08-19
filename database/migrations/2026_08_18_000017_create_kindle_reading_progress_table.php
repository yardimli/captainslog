<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kindle_reading_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->constrained()->cascadeOnDelete();
            $table->string('book_key', 64);
            $table->string('asin', 32)->nullable();
            $table->string('title', 500);
            $table->string('author', 500)->nullable();
            $table->decimal('percentage_read', 5, 2)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('client_id', 64);
            $table->timestamp('observed_at');
            $table->timestamps();

            $table->index(['user_id', 'observed_at']);
            $table->index(['daily_log_id', 'book_key']);
            $table->index(['user_id', 'book_key', 'observed_at'], 'kindle_user_book_observed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kindle_reading_progress');
    }
};
