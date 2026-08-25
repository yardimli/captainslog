<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->string('city')->nullable()->after('location_accuracy');
            $table->string('suburb')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('task_events', function (Blueprint $table) {
            $table->dropColumn(['city', 'suburb']);
        });
    }
};
