<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('place_name');
        });

        Schema::table('note_versions', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('content_format');
        });
    }

    public function down(): void
    {
        Schema::table('note_versions', fn (Blueprint $table) => $table->dropColumn('color'));
        Schema::table('notes', fn (Blueprint $table) => $table->dropColumn('color'));
    }
};
