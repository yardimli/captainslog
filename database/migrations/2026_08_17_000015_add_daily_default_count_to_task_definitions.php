<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->unsignedSmallInteger('daily_default_count')->default(1)->after('is_sticky');
        });
    }

    public function down(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('daily_default_count');
        });
    }
};
