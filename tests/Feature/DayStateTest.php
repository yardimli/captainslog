<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DailyLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_navigation_can_load_a_json_state_document(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-24']);
        $log->blocks()->create(['type' => 'text', 'emoji' => '🧭', 'content' => 'Structured payload', 'occurred_at' => '2026-08-24 09:15:00']);

        $response = $this->actingAs($user)
            ->withHeader('X-Day-State', 'json')
            ->get(route('logs.show', '2026-08-24'));

        $response->assertOk()
            ->assertHeader('Server-Timing')
            ->assertJsonPath('date', '2026-08-24')
            ->assertJsonStructure([
                'date', 'url', 'title', 'fetched_at',
                'navigation' => ['previous_url', 'today_url', 'next_url', 'calendar_url'],
                'log' => ['id', 'create_block_url', 'chat_url', 'image_url'],
                'tasks', 'timeline',
            ]);

        $response->assertJsonMissingPath('main_html')->assertJsonMissingPath('navigation_html');
        $response->assertJsonFragment(['type' => 'text', 'content' => 'Structured payload', 'emoji' => '🧭']);
    }

    public function test_authenticated_shell_contains_the_expired_session_login_overlay(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('calendar'))
            ->assertOk()
            ->assertSee('data-session-expired-overlay', false)
            ->assertSee('data-sync-status', false)
            ->assertSee('data-session-keepalive-url', false)
            ->assertSee('data-login-url', false);

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('function showSessionExpired()', $script);
        $this->assertStringContainsString("'X-Day-State': 'json'", $script);
        $this->assertStringContainsString('mutateDayState', $script);
        $this->assertStringContainsString('function renderTimelineItem(item)', $script);
        $this->assertStringContainsString("const supportsDayStateNavigation = () => Boolean(document.querySelector('#daily-log-page-container'))", $script);
        $this->assertStringContainsString('if (!supportsDayStateNavigation() || url.origin', $script);
        $this->assertStringContainsString('const backgroundSyncQueue = new Map()', $script);
        $this->assertStringContainsString('scheduleSyncRun(key, entry)', $script);
    }
}
