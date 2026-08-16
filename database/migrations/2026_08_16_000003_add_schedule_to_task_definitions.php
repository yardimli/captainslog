<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->string('recurrence_type', 16)->default('daily')->after('is_sticky');
            $table->json('recurrence_days')->nullable()->after('recurrence_type');
            $table->json('scheduled_times')->nullable()->after('recurrence_days');
            $table->index(['user_id', 'is_active', 'recurrence_type'], 'tasks_user_active_recurrence_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropIndex('tasks_user_active_recurrence_index');
            $table->dropColumn(['recurrence_type', 'recurrence_days', 'scheduled_times']);
        });
    }
};
