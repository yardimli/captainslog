<?php

namespace Tests\Feature;

use App\Models\ApiCall;
use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptainsLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_and_dated_log_are_refreshable_and_owned(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/calendar/2026-08-15?view=week')->assertOk()->assertSee('Calendar');
        $this->get('/logs/2026-08-15')->assertOk()->assertSee('Saturday, August 15, 2026');
        $this->get('/logs/2026-08-15')->assertOk()->assertSee('Saturday, August 15, 2026');
        $this->assertDatabaseHas('daily_logs', ['user_id' => $user->id, 'log_date' => '2026-08-15 00:00:00']);
    }

    public function test_daily_log_heading_has_previous_today_next_and_calendar_navigation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/logs/2026-08-15')->assertOk();

        $response->assertSee('href="'.route('logs.show', '2026-08-14').'"', false)
            ->assertSee('href="'.route('logs.show', today()->toDateString()).'"', false)
            ->assertSee('href="'.route('logs.show', '2026-08-16').'"', false)
            ->assertSee('href="'.route('calendar', '2026-08-15').'?view=week"', false);
    }

    public function test_task_buttons_accept_custom_browser_colors_and_legacy_colors_still_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'name' => 'Hydration alert',
            'color' => '#12Ab9F',
            'is_sticky' => '1',
            'recurrence_type' => 'daily',
            'scheduled_times_text' => '09:00',
        ])->assertRedirect(route('tasks.index'));

        $custom = TaskDefinition::where('user_id', $user->id)->where('name', 'Hydration alert')->firstOrFail();
        $legacy = TaskDefinition::create(['user_id' => $user->id, 'name' => 'Legacy event', 'color' => 'rose']);
        $this->assertSame('#12ab9f', $custom->color);
        $this->assertSame('#12ab9f', $custom->color_hex);
        $this->assertSame('#e11d48', $legacy->color_hex);
        $this->actingAs($user)->post(route('tasks.store'), ['name' => 'Bad color', 'color' => 'javascript:red'])->assertSessionHasErrors('color');
    }

    public function test_tasks_support_daily_weekly_and_monthly_recurrence_with_time_slots(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('tasks.store'), [
            'name' => 'Saturday rounds',
            'color' => '#4f46e5',
            'is_sticky' => '1',
            'recurrence_type' => 'weekly',
            'weekdays' => [6],
            'scheduled_times_text' => '08:00, 17:00',
        ])->assertRedirect(route('tasks.index'));

        $task = TaskDefinition::where('name', 'Saturday rounds')->firstOrFail();
        $this->assertSame('weekly', $task->recurrence_type);
        $this->assertSame([6], $task->recurrence_days);
        $this->assertSame(['08:00', '17:00'], $task->scheduled_times);

        $mondayTask = TaskDefinition::create([
            'user_id' => $user->id,
            'name' => 'Monday rounds',
            'recurrence_type' => 'weekly',
            'recurrence_days' => [1],
            'scheduled_times' => ['08:00'],
            'is_sticky' => true,
        ]);

        $this->get('/logs/2026-08-15')->assertOk()
            ->assertSee('Saturday rounds')
            ->assertDontSee('Monday rounds');
        $saturdayLog = DailyLog::where('user_id', $user->id)->whereDate('log_date', '2026-08-15')->firstOrFail();
        $this->postJson(route('events.store', [$saturdayLog, $mondayTask]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This event is not scheduled for this day.');

        $this->post(route('tasks.store'), [
            'name' => 'Missing slot',
            'color' => '#4f46e5',
            'is_sticky' => '1',
            'recurrence_type' => 'monthly',
            'month_days_text' => '1, 15',
        ])->assertSessionHasErrors('scheduled_times_text');
    }

    public function test_daily_timeline_orders_real_entries_around_scheduled_sticky_events(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        TaskDefinition::create([
            'user_id' => $user->id,
            'name' => 'Evening check',
            'is_sticky' => true,
            'recurrence_type' => 'daily',
            'scheduled_times' => ['17:00'],
        ]);
        $log->blocks()->forceCreate(['type' => 'text', 'content' => 'Before the scheduled slot', 'position' => 1, 'created_at' => '2026-08-15 13:00:00', 'updated_at' => '2026-08-15 13:00:00']);
        $log->blocks()->forceCreate(['type' => 'text', 'content' => 'After the scheduled slot', 'position' => 2, 'created_at' => '2026-08-15 19:00:00', 'updated_at' => '2026-08-15 19:00:00']);

        $this->actingAs($user)->get('/logs/2026-08-15')->assertOk()
            ->assertSeeInOrder(['Before the scheduled slot', 'Evening check', 'After the scheduled slot'])
            ->assertSee('data-hour="17"', false);
    }

    public function test_user_can_create_edit_and_delete_a_text_block_but_not_another_users_block(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $response = $this->actingAs($user)->postJson(route('blocks.store', $log), ['type' => 'text', 'content' => 'Steady as she goes.'])->assertCreated();
        $blockId = $response->json('block.id');
        $this->patchJson("/blocks/$blockId", ['content' => 'Course corrected.'])->assertOk();
        $this->actingAs(User::factory()->create())->deleteJson("/blocks/$blockId")->assertForbidden();
        $this->actingAs($user)->deleteJson("/blocks/$blockId")->assertOk();
        $this->assertDatabaseMissing('log_blocks', ['id' => $blockId]);
    }

    public function test_task_event_is_committed_before_optional_notes_and_requires_configured_value(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $task = TaskDefinition::create(['user_id' => $user->id, 'name' => 'Stress level', 'options' => ['1', '2', '3', '4', '5'], 'is_sticky' => true]);
        $this->actingAs($user)->postJson(route('events.store', [$log, $task]), [])->assertUnprocessable();
        Carbon::setTestNow('2026-08-15 15:45:00');
        $response = $this->postJson(route('events.store', [$log, $task]), ['value' => '4'])->assertCreated()->assertJsonPath('count', 1);
        $eventId = $response->json('event.id');
        $this->assertDatabaseHas('task_events', ['id' => $eventId, 'selected_value' => '4', 'occurred_at' => '2026-08-15 15:45:00']);
        Carbon::setTestNow();
        $this->patch(route('events.update', $eventId), ['notes' => 'Recovered after a walk.'])->assertRedirect();
        $this->assertDatabaseHas('log_blocks', ['content' => 'Recovered after a walk.']);
    }

    public function test_media_upload_is_tracked_and_stored_privately(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $response = $this->actingAs($user)->postJson(route('attachments.store', $log), ['file' => UploadedFile::fake()->image('horizon.jpg')])->assertCreated();
        $this->assertDatabaseHas('attachments', ['id' => $response->json('attachment.id'), 'type' => 'image', 'disk' => 'local']);
        Storage::disk('local')->assertExists($response->json('attachment.path'));
    }

    public function test_recording_controls_expose_visible_status_and_supported_browser_formats(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);

        $this->actingAs($user)->get('/logs/2026-08-15')->assertOk()
            ->assertSee('data-recording-status', false)
            ->assertSee('Your browser will ask for microphone or camera permission.');

        $this->postJson(route('attachments.store', $log), [
            'file' => UploadedFile::fake()->create('voice-note.ogg', 64, 'audio/ogg'),
        ])->assertCreated()->assertJsonPath('attachment.type', 'audio');
    }

    public function test_openrouter_chat_reply_and_cost_are_added_to_the_day(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([
            'id' => 'gen-123', 'model' => 'test/model', 'choices' => [['message' => ['content' => 'A concise reflection.']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 5, 'total_tokens' => 17, 'cost' => 0.0012],
        ], 200)]);
        $user = User::factory()->create(['openrouter_api_key' => 'sk-test']);
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $this->actingAs($user)->postJson(route('openrouter.chat', $log), ['message' => 'Summarize this day.', 'model' => 'test/model'])->assertCreated();
        $this->assertDatabaseHas('log_blocks', ['daily_log_id' => $log->id, 'type' => 'chat_assistant', 'content' => 'A concise reflection.']);
        $this->assertDatabaseHas('api_calls', ['daily_log_id' => $log->id, 'operation' => 'chat', 'total_tokens' => 17]);
        $this->assertSame('0.00120000', ApiCall::first()->cost);
    }
}
