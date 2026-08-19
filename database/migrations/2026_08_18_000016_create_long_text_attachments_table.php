<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('long_text_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('format', 16)->default('text');
            $table->longText('content');
            $table->timestamps();
            $table->index(['user_id', 'daily_log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('long_text_attachments');
    }
};
