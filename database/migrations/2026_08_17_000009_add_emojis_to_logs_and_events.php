<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_definitions', function (Blueprint $table) {
            $table->string('emoji', 32)->default('✅')->after('name');
        });

        Schema::table('log_blocks', function (Blueprint $table) {
            $table->string('emoji', 32)->default('📝')->after('type');
        });

        DB::table('task_definitions')->update(['emoji' => '✅']);
        DB::table('log_blocks')->where('type', 'event')->update(['emoji' => '✅']);
        DB::table('log_blocks')->where('type', 'chat_user')->update(['emoji' => '💬']);
        DB::table('log_blocks')->where('type', 'chat_assistant')->update(['emoji' => '🤖']);
        DB::table('log_blocks')->where('type', 'generated_image')->update(['emoji' => '🎨']);
        DB::table('log_blocks')->where('type', 'media')->update(['emoji' => '📎']);

        $imageBlockIds = DB::table('attachments')
            ->join('log_blocks', 'log_blocks.id', '=', 'attachments.log_block_id')
            ->where('attachments.type', 'image')
            ->where('log_blocks.type', 'media')
            ->pluck('attachments.log_block_id');
        foreach ($imageBlockIds->chunk(500) as $ids) {
            DB::table('log_blocks')->whereIn('id', $ids)->update(['emoji' => '🖼️']);
        }
    }

    public function down(): void
    {
        Schema::table('log_blocks', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });

        Schema::table('task_definitions', function (Blueprint $table) {
            $table->dropColumn('emoji');
        });
    }
};
