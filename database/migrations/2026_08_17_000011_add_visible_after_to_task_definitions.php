<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->time('visible_after')->nullable()->after('scheduled_times');
        });
    }

    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('visible_after');
        });
    }
};
