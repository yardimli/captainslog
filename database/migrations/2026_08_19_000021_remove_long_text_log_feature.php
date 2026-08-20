<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('long_text_attachments')) {
            return;
        }

        DB::table('long_text_attachments')->orderBy('id')->each(function (object $longText) {
            $block = DB::table('log_blocks')->find($longText->log_block_id);
            if (! $block) {
                return;
            }

            $metadata = json_decode($block->metadata ?: '[]', true) ?: [];
            $metadata['converted_from_long_text'] = [
                'format' => $longText->format,
                'converted_at' => now()->toIso8601String(),
            ];

            DB::table('log_blocks')->where('id', $block->id)->update([
                'type' => 'text',
                'content' => $longText->content,
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        });

        Schema::drop('long_text_attachments');
    }

    public function down(): void
    {
        Schema::create('long_text_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('format', 16)->default('text');
            $table->longText('content');
            $table->timestamps();
            $table->index(['user_id', 'daily_log_id']);
        });
    }
};
