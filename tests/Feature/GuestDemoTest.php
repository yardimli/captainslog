<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\User;
use App\Services\GuestDemoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $response = $this->get('/')->assertOk()->assertSee('Live guest simulation')->assertSee("Captain's log, present day");

        $response->assertCookie(GuestDemoService::COOKIE);
        $guest = User::where('is_guest', true)->firstOrFail();
        $this->assertCount(8, $guest->dailyLogs);
        $this->assertDatabaseHas('daily_logs', ['user_id' => $guest->id, 'log_date' => '2026-08-08 00:00:00']);
        $this->assertDatabaseHas('daily_logs', ['user_id' => $guest->id, 'log_date' => '2026-08-15 00:00:00']);
        $this->assertDatabaseHas('task_definitions', ['user_id' => $guest->id, 'name' => 'Dog medication']);
    }

    public function test_guest_cookie_reconnects_to_the_same_workspace_and_actions_are_persistent(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $first = $this->get('/');
        $token = $first->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $guest = User::where('is_guest', true)->firstOrFail();
        $this->assertSame($guest->guest_token_hash, hash('sha256', $token));
        $log = DailyLog::where('user_id', $guest->id)->whereDate('log_date', today())->firstOrFail();

        $response = $this->withCredentials()->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->postJson(route('demo.blocks.store', $log), ['content' => 'A guest supplemental log.'])->assertCreated();
        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->get('/')->assertOk()->assertSee('A guest supplemental log.');
        $this->assertSame(1, User::where('is_guest', true)->count());

        $task = TaskDefinition::where('user_id', $guest->id)->where('name', 'Yoga class')->firstOrFail();
        $this->withUnencryptedCookie(GuestDemoService::COOKIE, $token)->postJson(route('demo.events.store', [$log, $task]), ['value' => 'Gentle'])->assertCreated()->assertJsonPath('count', 1);
        $this->assertDatabaseHas('task_events', ['daily_log_id' => $log->id, 'selected_value' => 'Gentle']);
        $this->assertNotNull($response->json('block.id'));
    }

    public function test_one_guest_cannot_modify_another_guests_blocks(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $first = $this->get('/');
        $firstToken = $first->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $firstGuest = User::where('is_guest', true)->firstOrFail();
        $firstBlock = LogBlock::whereHas('dailyLog', fn ($query) => $query->where('user_id', $firstGuest->id))->firstOrFail();

        $second = $this->get('/');
        $secondToken = $second->getCookie(GuestDemoService::COOKIE, false)->getValue();
        $this->assertNotSame($firstToken, $secondToken);
        $this->withCredentials()->withUnencryptedCookie(GuestDemoService::COOKIE, $secondToken)->deleteJson(route('demo.blocks.destroy', $firstBlock))->assertForbidden();
        $this->assertDatabaseHas('log_blocks', ['id' => $firstBlock->id]);
    }
}
