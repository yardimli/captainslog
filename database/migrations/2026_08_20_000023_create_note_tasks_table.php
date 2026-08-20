<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('note_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'is_completed', 'position']);
            $table->index(['note_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_tasks');
    }
};
