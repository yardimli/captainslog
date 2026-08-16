<?php

namespace Tests\Feature;

use App\Models\ApiCall;
use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\User;
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
        ])->assertRedirect(route('tasks.index'));

        $custom = TaskDefinition::where('user_id', $user->id)->where('name', 'Hydration alert')->firstOrFail();
        $legacy = TaskDefinition::create(['user_id' => $user->id, 'name' => 'Legacy event', 'color' => 'rose']);
        $this->assertSame('#12ab9f', $custom->color);
        $this->assertSame('#12ab9f', $custom->color_hex);
        $this->assertSame('#e11d48', $legacy->color_hex);
        $this->actingAs($user)->post(route('tasks.store'), ['name' => 'Bad color', 'color' => 'javascript:red'])->assertSessionHasErrors('color');
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
        $response = $this->postJson(route('events.store', [$log, $task]), ['value' => '4'])->assertCreated()->assertJsonPath('count', 1);
        $eventId = $response->json('event.id');
        $this->assertDatabaseHas('task_events', ['id' => $eventId, 'selected_value' => '4']);
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
