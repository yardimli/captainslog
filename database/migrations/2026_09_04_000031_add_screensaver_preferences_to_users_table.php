<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('screensaver_enabled')->default(false)->after('default_chat_model');
            $table->string('screensaver_style', 40)->default('flying-toasters')->after('screensaver_enabled');
            $table->unsignedSmallInteger('screensaver_wait_minutes')->default(10)->after('screensaver_style');
            $table->decimal('screensaver_speed', 3, 2)->default(1)->after('screensaver_wait_minutes');
            $table->string('screensaver_message', 120)->default('OUT TO LUNCH')->after('screensaver_speed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'screensaver_enabled',
                'screensaver_style',
                'screensaver_wait_minutes',
                'screensaver_speed',
                'screensaver_message',
            ]);
        });
    }
};
