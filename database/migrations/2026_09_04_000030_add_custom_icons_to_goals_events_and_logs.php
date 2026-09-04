<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_definitions', fn (Blueprint $table) => $table->longText('icon_data')->nullable());
        Schema::table('goals', fn (Blueprint $table) => $table->longText('icon_data')->nullable());
        Schema::table('log_blocks', fn (Blueprint $table) => $table->longText('icon_data')->nullable());
    }

    public function down(): void
    {
        Schema::table('task_definitions', fn (Blueprint $table) => $table->dropColumn('icon_data'));
        Schema::table('goals', fn (Blueprint $table) => $table->dropColumn('icon_data'));
        Schema::table('log_blocks', fn (Blueprint $table) => $table->dropColumn('icon_data'));
    }
};
