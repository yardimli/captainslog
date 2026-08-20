<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_schema_contains_the_required_tables(): void
    {
        $tables = [
            'notebook_stacks',
            'notebooks',
            'notes',
            'note_versions',
            'note_tasks',
            'note_tags',
            'note_tag',
            'note_links',
            'note_log_links',
            'note_attachments',
            'saved_note_searches',
            'note_shortcuts',
            'note_spaces',
            'note_space_members',
            'note_space_items',
            'note_shares',
            'note_public_links',
            'note_user_settings',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected the {$table} table to exist.");
        }
    }

    public function test_notes_link_to_logs_by_date_and_exact_time_without_owning_schedule_data(): void
    {
        $this->assertTrue(Schema::hasColumns('note_log_links', [
            'note_id',
            'daily_log_id',
            'log_block_id',
            'occurred_at',
        ]));

        $this->assertFalse(Schema::hasColumn('notes', 'due_at'));
        $this->assertFalse(Schema::hasColumn('notes', 'reminder_at'));
        $this->assertFalse(Schema::hasColumn('notes', 'calendar_event_id'));
        $this->assertTrue(Schema::hasColumns('note_tasks', [
            'note_id',
            'title',
            'is_completed',
            'completed_at',
            'position',
        ]));
        $this->assertFalse(Schema::hasColumn('note_tasks', 'due_at'));
        $this->assertFalse(Schema::hasColumn('note_tasks', 'reminder_at'));
        $this->assertFalse(Schema::hasColumn('note_tasks', 'recurrence'));
        $this->assertTrue(Schema::hasColumn('notes', 'color'));
        $this->assertTrue(Schema::hasColumn('note_versions', 'color'));
        $this->assertFalse(Schema::hasTable('long_text_attachments'));
    }
}
