<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false)->after('password')->index();
            $table->char('guest_token_hash', 64)->nullable()->after('is_guest')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['guest_token_hash']);
            $table->dropIndex(['is_guest']);
            $table->dropColumn(['is_guest', 'guest_token_hash']);
        });
    }
};
