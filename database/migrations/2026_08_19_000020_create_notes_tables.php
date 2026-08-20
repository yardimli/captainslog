<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notebook_stacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'position']);
        });

        Schema::create('notebooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notebook_stack_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_pinned', 'position']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notebook_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500)->default('');
            $table->longText('content')->nullable();
            $table->json('content_json')->nullable();
            $table->longText('plain_text')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('content_format', 20)->default('html');
            $table->string('source_type', 40)->default('manual');
            $table->string('source_url', 2048)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('place_name', 255)->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_template')->default(false);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'notebook_id', 'updated_at']);
            $table->index(['user_id', 'is_archived', 'is_pinned']);
            $table->index(['user_id', 'is_template']);
        });

        Schema::create('note_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 500)->default('');
            $table->longText('content')->nullable();
            $table->json('content_json')->nullable();
            $table->longText('plain_text')->nullable();
            $table->string('content_format', 20)->default('html');
            $table->string('change_source', 40)->default('autosave');
            $table->string('change_summary', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['note_id', 'version_number']);
            $table->index(['note_id', 'created_at']);
        });

        Schema::create('note_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('note_tags')->nullOnDelete();
            $table->string('name', 120);
            $table->string('color', 20)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'parent_id']);
        });

        Schema::create('note_tag', function (Blueprint $table) {
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['note_id', 'note_tag_id']);
            $table->index(['note_tag_id', 'note_id']);
        });

        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_note_id')->constrained('notes')->cascadeOnDelete();
            $table->foreignId('target_note_id')->constrained('notes')->cascadeOnDelete();
            $table->string('source_block_key', 120)->nullable();
            $table->string('target_anchor', 255)->nullable();
            $table->string('display_text', 500)->nullable();
            $table->timestamps();

            $table->index(['source_note_id', 'target_note_id']);
            $table->index(['target_note_id', 'source_note_id']);
        });

        Schema::create('note_log_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_block_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('label', 255)->nullable();
            $table->timestamps();

            $table->index(['note_id', 'occurred_at']);
            $table->index(['daily_log_id', 'occurred_at']);
        });

        Schema::create('note_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->string('block_key', 120)->nullable();
            $table->string('type', 32);
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name', 500)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('processing_status', 32)->default('ready');
            $table->longText('extracted_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['note_id', 'position']);
            $table->index(['user_id', 'type']);
            $table->index(['processing_status', 'created_at']);
        });

        Schema::create('saved_note_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('query')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'position']);
        });

        Schema::create('note_shortcuts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shortcutable_type', 80);
            $table->unsignedBigInteger('shortcutable_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'shortcutable_type', 'shortcutable_id'], 'note_shortcuts_target_unique');
            $table->index(['user_id', 'position']);
        });

        Schema::create('note_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('visibility', 20)->default('private');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_user_id', 'name']);
        });

        Schema::create('note_space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 24)->default('view');
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['note_space_id', 'user_id']);
            $table->index(['user_id', 'permission']);
        });

        Schema::create('note_space_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('notebook_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['note_space_id', 'note_id']);
            $table->unique(['note_space_id', 'notebook_id']);
            $table->index(['note_space_id', 'position']);
        });

        Schema::create('note_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('shareable_type', 80);
            $table->unsignedBigInteger('shareable_id');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('recipient_email')->nullable();
            $table->string('permission', 24)->default('view');
            $table->char('invitation_token_hash', 64)->nullable()->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['shareable_type', 'shareable_id']);
            $table->index(['recipient_user_id', 'accepted_at']);
            $table->index(['recipient_email', 'accepted_at']);
        });

        Schema::create('note_public_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('permission', 24)->default('view');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['note_id', 'expires_at']);
        });

        Schema::create('note_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('default_notebook_id')->nullable()->constrained('notebooks')->nullOnDelete();
            $table->string('default_list_view', 24)->default('snippet');
            $table->string('default_sort', 32)->default('updated_at');
            $table->string('default_sort_direction', 4)->default('desc');
            $table->json('editor_preferences')->nullable();
            $table->json('sidebar_preferences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_user_settings');
        Schema::dropIfExists('note_public_links');
        Schema::dropIfExists('note_shares');
        Schema::dropIfExists('note_space_items');
        Schema::dropIfExists('note_space_members');
        Schema::dropIfExists('note_spaces');
        Schema::dropIfExists('note_shortcuts');
        Schema::dropIfExists('saved_note_searches');
        Schema::dropIfExists('note_attachments');
        Schema::dropIfExists('note_log_links');
        Schema::dropIfExists('note_links');
        Schema::dropIfExists('note_tag');
        Schema::dropIfExists('note_tags');
        Schema::dropIfExists('note_versions');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('notebooks');
        Schema::dropIfExists('notebook_stacks');
    }
};
