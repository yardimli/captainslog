<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\User;
use App\Services\GuestDemoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuestDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_landing_page_creates_an_isolated_guest_and_eight_real_date_logs(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');

        $response = $this->get('/')->assertOk()
            ->assertSee('Live guest simulation')
            ->assertSee('Total record, present day')
            ->assertSee('images/demo-yoga-observation-deck.png', false)
            ->assertSee('images/demo-pet-medication.png', false)
            ->assertSee('data-emoji-picker', false)
            ->assertSee('data-composer-note-form', false)
            ->assertSee('data-edit-emoji=', false)
            ->assertSee('Image attached to this demo log entry');

        $response->assertCookie(GuestDemoService::COOKIE);
        $guest = User::where('is_guest', true)->firstOrFail();
        $this->assertCount(8, $guest->dailyLogs);
        $this->assertDatabaseHas('daily_logs', ['user_id' => $guest->id, 'log_date' => '2026-08-08 00:00:00']);
        $this->assertDatabaseHas('daily_logs', ['user_id' => $guest->id, 'log_date' => '2026-08-15 00:00:00']);
        $this->assertSame(2, $guest->demo_seed_version);
        $this->assertDatabaseHas('task_definitions', ['user_id' => $guest->id, 'name' => 'Dog medication', 'emoji' => '💊']);
        $this->assertDatabaseCount('attachments', 2);
        $this->assertDatabaseHas('log_blocks', ['daily_log_id' => $guest->dailyLogs()->whereDate('log_date', today())->value('id'), 'type' => 'generated_image', 'emoji' => '🎨']);
    }

    public function test_guest_cookie_reconnects_to_the_same_workspace_and_actions_are_persistent(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $first = $this->get('/');
        $token = $first->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $guest = User::where('is_guest', true)->firstOrFail();
        $this->assertSame($guest->guest_token_hash, hash('sha256', $token));
        $log = DailyLog::where('user_id', $guest->id)->whereDate('log_date', today())->firstOrFail();

        $response = $this->withCredentials()->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->postJson(route('demo.blocks.store', $log), ['content' => 'A guest supplemental log.', 'emoji' => '🚀', 'occurred_at' => '08:35'])->assertCreated();
        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->get('/')->assertOk()->assertSee('A guest supplemental log.');
        $this->assertSame(1, User::where('is_guest', true)->count());
        $this->assertDatabaseHas('log_blocks', ['id' => $response->json('block.id'), 'emoji' => '🚀', 'occurred_at' => '2026-08-15 08:35:00']);

        $task = TaskDefinition::where('user_id', $guest->id)->where('name', 'Yoga class')->firstOrFail();
        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->postJson(route('demo.events.store', [$log, $task]), ['value' => 'Gentle'])->assertCreated()->assertJsonPath('count', 1);
        $this->assertDatabaseHas('task_events', ['daily_log_id' => $log->id, 'selected_value' => 'Gentle']);
        $this->assertDatabaseHas('log_blocks', ['daily_log_id' => $log->id, 'type' => 'event', 'emoji' => '🧘']);
        $this->assertNotNull($response->json('block.id'));
    }

    public function test_demo_image_delete_removes_tracking_but_not_the_shared_asset_or_reseeds_it(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $first = $this->get('/');
        $token = $first->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $guest = User::where('is_guest', true)->firstOrFail();
        $attachment = Attachment::where('user_id', $guest->id)->where('path', 'demo-yoga-observation-deck.png')->firstOrFail();
        $blockId = $attachment->log_block_id;

        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->get(route('demo.attachments.show', $attachment))->assertOk()->assertHeader('content-type', 'image/png');
        $this->withCredentials()->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->deleteJson(route('demo.blocks.destroy', $blockId))->assertOk();
        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('log_blocks', ['id' => $blockId]);
        $this->assertTrue(Storage::disk('demo_assets')->exists('demo-yoga-observation-deck.png'));

        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->get('/')->assertOk();
        $this->assertDatabaseMissing('attachments', ['user_id' => $guest->id, 'path' => 'demo-yoga-observation-deck.png']);
    }

    public function test_one_guest_cannot_modify_another_guests_blocks(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $first = $this->get('/');
        $firstToken = $first->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $firstGuest = User::where('is_guest', true)->firstOrFail();
        $firstBlock = LogBlock::whereHas('dailyLog', fn ($query) => $query->where('user_id', $firstGuest->id))->firstOrFail();
        $firstAttachment = Attachment::where('user_id', $firstGuest->id)->firstOrFail();

        $second = $this->get('/');
        $secondToken = $second->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $this->assertNotSame($firstToken, $secondToken);
        $this->withCredentials()->withUnencryptedCookie(GuestDemoService::COOKIE, $secondToken)->deleteJson(route('demo.blocks.destroy', $firstBlock))->assertForbidden();
        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $secondToken)->get(route('demo.attachments.show', $firstAttachment))->assertForbidden();
        $this->assertDatabaseHas('log_blocks', ['id' => $firstBlock->id]);
    }
}
