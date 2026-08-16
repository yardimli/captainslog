<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('time_format', 2)->default('24')->after('openrouter_api_key');
            $table->unsignedTinyInteger('week_starts_on')->default(1)->after('time_format');
            $table->string('default_chat_model', 191)->nullable()->after('week_starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['time_format', 'week_starts_on', 'default_chat_model']);
        });
    }
};
