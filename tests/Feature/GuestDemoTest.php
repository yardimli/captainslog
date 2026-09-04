<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\DailyLog;
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

    public function test_landing_page_promotes_the_demo_without_creating_users_or_log_data(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');

        $response = $this->get('/')->assertOk()
            ->assertSee('Live simulation')
            ->assertSee('Open the read-only demo')
            ->assertSee('href="'.route('demo.enter').'"', false)
            ->assertDontSee('data-composer-note-form', false)
            ->assertDontSee('Desktop activity · 3 apps · 1 hr 25 min');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('daily_logs', 0);
    }

    public function test_opening_demo_logs_into_one_seeded_account_without_recreating_entries(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');

        $this->get(route('demo.enter'))->assertRedirect(route('calendar'));
        $guest = User::where('email', GuestDemoService::EMAIL)->where('is_guest', true)->firstOrFail();
        $this->assertAuthenticatedAs($guest);
        $this->assertCount(8, $guest->dailyLogs);
        $initialBlockCount = $guest->dailyLogs()->withCount('blocks')->get()->sum('blocks_count');

        $this->get(route('demo.enter'))->assertRedirect(route('calendar'));
        $this->assertSame(1, User::where('is_guest', true)->count());
        $this->assertSame($initialBlockCount, $guest->dailyLogs()->withCount('blocks')->get()->sum('blocks_count'));
        $this->get(route('calendar'))->assertOk()
            ->assertSee('Read-only demo')
            ->assertSee('data-demo-readonly="true"', false);

        $todayLogId = $guest->dailyLogs()->whereDate('log_date', today())->value('id');
        foreach (['sensor_google_calendar', 'sensor_desktop', 'sensor_browser', 'sensor_mobile_browser', 'sensor_github', 'sensor_kindle', 'generated_image'] as $sourceType) {
            $this->assertDatabaseHas('log_blocks', ['daily_log_id' => $todayLogId, 'type' => $sourceType]);
        }
        $this->assertDatabaseCount('attachments', 2);
        $this->assertDatabaseHas('log_blocks', ['daily_log_id' => $todayLogId, 'content' => 'totallog']);
    }

    public function test_demo_uses_real_detail_views_and_rejects_all_mutations(): void
    {
        Carbon::setTestNow('2026-08-15 09:00:00');
        $this->get(route('demo.enter'));
        $guest = User::where('is_guest', true)->firstOrFail();
        $log = DailyLog::where('user_id', $guest->id)->whereDate('log_date', today())->firstOrFail();
        $attachment = Attachment::where('user_id', $guest->id)->where('path', 'demo-dog-walk.png')->firstOrFail();
        $blockCount = $log->blocks()->count();
        $logCount = $guest->dailyLogs()->count();

        $this->get(route('logs.show', today()->toDateString()))->assertOk()
            ->assertSee('maps.example')
            ->assertSee('Add read-only demo calendar')
            ->assertSee('data-timeline-github', false)
            ->assertSee('data-timeline-browsing', false);
        $this->get(route('attachments.show', $attachment))->assertOk()->assertHeader('content-type', 'image/png');
        $this->postJson(route('blocks.store', $log), ['content' => 'Should not save'])->assertForbidden()
            ->assertSee('The demo is read-only');
        $this->putJson(route('password.update'), [
            'current_password' => 'anything',
            'password' => 'Changed-password-123!',
            'password_confirmation' => 'Changed-password-123!',
        ])->assertForbidden();
        $this->get(route('logs.show', today()->addMonth()->toDateString()))
            ->assertRedirect(route('calendar'));
        $this->assertSame($blockCount, $log->blocks()->count());
        $this->assertSame($logCount, $guest->dailyLogs()->count());
        $this->assertTrue(Storage::disk('demo_assets')->exists('demo-dog-walk.png'));
    }
}
