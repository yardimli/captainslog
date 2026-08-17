<?php

namespace Tests\Feature;

use App\Models\BrowsingActivity;
use App\Models\DailyLog;
use App\Models\Sensor;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SensorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_validate_link_toggle_and_unlink_github(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'octocat'], 200),
        ]);

        $this->actingAs($user)->post(route('sensors.github.link'), [
            'github_username' => 'octocat',
            'github_token' => 'github_pat_secret-value',
        ])->assertRedirect()->assertSessionHas('status', 'GitHub linked and enabled.');

        $sensor = Sensor::where('user_id', $user->id)->where('type', Sensor::GITHUB)->firstOrFail();
        $this->assertTrue($sensor->enabled);
        $this->assertSame('octocat', $sensor->username);
        $this->assertSame('github_pat_secret-value', $sensor->token);
        $this->assertNotSame('github_pat_secret-value', DB::table('sensors')->where('id', $sensor->id)->value('token'));

        $this->get(route('sensors.index'))->assertOk()
            ->assertSee('GitHub commits')
            ->assertSee('@octocat')
            ->assertSee('data-sensor-enable', false)
            ->assertSee('data-confirm-sensor-unlink', false);

        $this->patchJson(route('sensors.github.toggle'), ['enabled' => false])
            ->assertOk()->assertJsonPath('message', 'GitHub sensor disabled.');
        $this->assertFalse($sensor->fresh()->enabled);

        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $block = $log->blocks()->create(['type' => 'sensor_github', 'content' => 'captainslog', 'metadata' => ['sensor' => 'github', 'github_sha' => 'abc']]);
        $this->delete(route('sensors.github.unlink'))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('sensors', ['id' => $sensor->id]);
        $this->assertDatabaseHas('log_blocks', ['id' => $block->id, 'content' => 'captainslog']);
    }

    public function test_github_link_rejects_a_token_for_another_username(): void
    {
        $user = User::factory()->create();
        Http::fake(['api.github.com/user' => Http::response(['login' => 'someone-else'], 200)]);

        $this->actingAs($user)->post(route('sensors.github.link'), [
            'github_username' => 'octocat',
            'github_token' => 'wrong-account-token',
        ])->assertSessionHasErrors('github_token');

        $this->assertDatabaseCount('sensors', 0);
    }

    public function test_past_github_days_sync_once_and_empty_days_are_finalized(): void
    {
        Carbon::setTestNow('2026-08-17 14:00:00');
        $user = User::factory()->create();
        Sensor::create(['user_id' => $user->id, 'type' => Sensor::GITHUB, 'username' => 'octocat', 'token' => 'secret', 'enabled' => true]);
        Http::fake(function (Request $request) {
            $query = $request->data()['q'] ?? '';
            if (str_contains($query, '2026-08-15')) {
                return Http::response([
                    'total_count' => 4,
                    'items' => [[
                        'sha' => 'commit-one',
                        'html_url' => 'https://github.com/octocat/captainslog/commit/commit-one',
                        'repository' => ['name' => 'captainslog', 'full_name' => 'octocat/captainslog'],
                        'commit' => ['message' => 'Add navigation controls', 'author' => ['date' => '2026-08-15T09:35:00Z']],
                    ], [
                        'sha' => 'commit-two',
                        'html_url' => 'https://github.com/octocat/captainslog/commit/commit-two',
                        'repository' => ['name' => 'captainslog', 'full_name' => 'octocat/captainslog'],
                        'commit' => ['message' => "Group sensor entries\n\nMore details", 'author' => ['date' => '2026-08-15T09:50:00Z']],
                    ], [
                        'sha' => 'commit-three',
                        'html_url' => 'https://github.com/octocat/captainslog/commit/commit-three',
                        'repository' => ['name' => 'captainslog', 'full_name' => 'octocat/captainslog'],
                        'commit' => ['message' => 'Start the next hour', 'author' => ['date' => '2026-08-15T10:05:00Z']],
                    ], [
                        'sha' => 'commit-other',
                        'html_url' => 'https://github.com/octocat/side-project/commit/commit-other',
                        'repository' => ['name' => 'side-project', 'full_name' => 'octocat/side-project'],
                        'commit' => ['message' => 'Update the side project', 'author' => ['date' => '2026-08-15T09:45:00Z']],
                    ]],
                ]);
            }

            return Http::response(['total_count' => 0, 'items' => []]);
        });

        $this->actingAs($user)->get('/logs/2026-08-15')->assertOk()
            ->assertSee('captainslog')
            ->assertSee('2 commits')
            ->assertSee('data-timeline-github', false)
            ->assertSee('Add navigation controls')
            ->assertSee('Group sensor entries')
            ->assertSee('data-github-event-list', false);
        $pastLog = DailyLog::where('user_id', $user->id)->whereDate('log_date', '2026-08-15')->firstOrFail();
        $this->assertSame(3, $pastLog->blocks()->where('type', 'sensor_github')->count());
        $grouped = $pastLog->blocks()->where('type', 'sensor_github')->where('content', 'captainslog')->get()
            ->first(fn ($block) => $block->occurred_at->format('H') === '17');
        $this->assertNotNull($grouped);
        $this->assertCount(2, $grouped->metadata['commits']);
        $this->assertSame(['commit-one', 'commit-two'], collect($grouped->metadata['commits'])->pluck('sha')->all());
        $this->get('/logs/2026-08-15')->assertOk();
        $this->get('/logs/2026-08-16')->assertOk()->assertSee('No Git commits today');
        $this->get('/logs/2026-08-16')->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret')
            && $request->hasHeader('X-GitHub-Api-Version', '2022-11-28'));
        $this->assertDatabaseHas('log_blocks', ['type' => 'sensor_github', 'content' => 'captainslog']);
        $this->assertDatabaseHas('log_blocks', ['type' => 'sensor_github', 'content' => 'No Git commits today']);
        $this->assertDatabaseCount('sensor_day_syncs', 2);
        Carbon::setTestNow();
    }

    public function test_existing_github_commit_blocks_are_consolidated_without_an_api_request(): void
    {
        Carbon::setTestNow('2026-08-17 14:00:00');
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        foreach ([['legacy-one', '09:10:00'], ['legacy-two', '09:45:00']] as [$sha, $time]) {
            $log->blocks()->forceCreate([
                'type' => 'sensor_github',
                'content' => 'captainslog',
                'occurred_at' => '2026-08-15 '.$time,
                'metadata' => ['sensor' => Sensor::GITHUB, 'github_sha' => $sha, 'repository' => 'octocat/captainslog', 'url' => 'https://github.com/octocat/captainslog/commit/'.$sha, 'empty' => false],
            ]);
        }
        Http::fake();

        $this->actingAs($user)->get('/logs/2026-08-15')->assertOk()
            ->assertSee('2 commits')
            ->assertSee('legacy-one')
            ->assertSee('legacy-two')
            ->assertSee('data-timeline-github', false);

        Http::assertNothingSent();
        $this->assertSame(1, $log->blocks()->where('type', 'sensor_github')->count());
        $events = $log->blocks()->where('type', 'sensor_github')->firstOrFail()->metadata['commits'];
        $this->assertSame(['legacy-one', 'legacy-two'], collect($events)->pluck('sha')->all());
        Carbon::setTestNow();
    }

    public function test_current_day_rechecks_and_replaces_empty_marker_when_a_commit_appears(): void
    {
        Carbon::setTestNow('2026-08-17 14:00:00');
        $user = User::factory()->create();
        Sensor::create(['user_id' => $user->id, 'type' => Sensor::GITHUB, 'username' => 'octocat', 'token' => 'secret', 'enabled' => true]);
        Http::fake(['api.github.com/search/commits*' => Http::sequence()
            ->push(['total_count' => 0, 'items' => []])
            ->push(['total_count' => 1, 'items' => [[
                'sha' => 'current-commit',
                'html_url' => 'https://github.com/octocat/today-project/commit/current-commit',
                'repository' => ['name' => 'today-project', 'full_name' => 'octocat/today-project'],
                'commit' => ['author' => ['date' => '2026-08-17T13:45:00Z']],
            ]]])
            ->push(['total_count' => 1, 'items' => [[
                'sha' => 'current-commit',
                'html_url' => 'https://github.com/octocat/today-project/commit/current-commit',
                'repository' => ['name' => 'today-project', 'full_name' => 'octocat/today-project'],
                'commit' => ['author' => ['date' => '2026-08-17T13:45:00Z']],
            ]]])]);

        $this->actingAs($user)->get('/logs/2026-08-17')->assertOk()->assertSee('No Git commits today');
        $secondLoad = $this->get('/logs/2026-08-17')->assertOk();
        $this->assertNull(Sensor::first()->fresh()->last_error, Sensor::first()->fresh()->last_error ?? 'GitHub sync should not fail.');
        $secondLoad->assertSee('today-project')->assertDontSee('No Git commits today');
        $this->get('/logs/2026-08-17')->assertOk()->assertSee('today-project');

        Http::assertSentCount(3);
        $this->assertDatabaseCount('log_blocks', 1);
        $this->assertDatabaseHas('log_blocks', ['type' => 'sensor_github', 'content' => 'today-project']);
        Carbon::setTestNow();
    }

    public function test_disabled_sensor_and_future_days_do_not_call_github(): void
    {
        Carbon::setTestNow('2026-08-17 14:00:00');
        $user = User::factory()->create();
        Sensor::create(['user_id' => $user->id, 'type' => Sensor::GITHUB, 'username' => 'octocat', 'token' => 'secret', 'enabled' => false]);
        Http::fake();

        $this->actingAs($user)->get('/logs/2026-08-17')->assertOk();
        $this->get('/logs/2026-08-18')->assertOk();
        Http::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_chrome_extension_pairs_with_a_random_key_and_can_be_unlinked(): void
    {
        $user = User::factory()->create();
        $key = str_repeat('pairing-key-', 6);

        $this->actingAs($user)->get(route('sensors.browser.pair', $key))
            ->assertRedirect(route('sensors.index'))
            ->assertSessionHas('status', 'Chrome browsing sensor paired and enabled.');

        $sensor = Sensor::where('user_id', $user->id)->where('type', Sensor::BROWSER)->firstOrFail();
        $this->assertTrue($sensor->enabled);
        $this->assertSame(hash('sha256', $key), $sensor->pairing_key_hash);
        $this->assertDatabaseMissing('sensors', ['pairing_key_hash' => $key]);
        $this->get(route('sensors.index'))->assertOk()
            ->assertSee('Chrome browsing')
            ->assertSee('Extension paired')
            ->assertSee('public/captainslog-chrome-extension')
            ->assertSee('data-confirm-browser-unlink', false);

        $this->delete(route('sensors.browser.unlink'))->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseMissing('sensors', ['id' => $sensor->id]);
    }

    public function test_browser_sensor_groups_domains_into_hourly_log_blocks_and_closes_after_inactivity(): void
    {
        Carbon::setTestNow('2026-08-17 10:00:00');
        $user = User::factory()->create();
        $key = str_repeat('b', 64);
        Sensor::create([
            'user_id' => $user->id,
            'type' => Sensor::BROWSER,
            'username' => 'Chrome extension',
            'pairing_key_hash' => hash('sha256', $key),
            'enabled' => true,
        ]);

        $send = function (string $url) use ($key) {
            return $this->withHeader('X-CaptainsLog-Key', $key)->postJson(route('api.sensors.browser.activity'), [
                'url' => $url,
                'observed_at' => now()->toIso8601String(),
                'client_id' => 'chrome-test-client',
            ]);
        };

        $send('https://docs.github.com/en/rest?private=query')->assertCreated()->assertJsonPath('domain', 'github.com');
        Carbon::setTestNow('2026-08-17 10:01:00');
        $send('https://github.com/yardimli/captainslog')->assertCreated()->assertJsonPath('domain', 'github.com');
        Carbon::setTestNow('2026-08-17 10:02:00');
        $send('https://news.ycombinator.com/item?id=1')->assertCreated()->assertJsonPath('domain', 'ycombinator.com');

        $this->assertDatabaseCount('daily_logs', 1);
        $this->assertDatabaseCount('browsing_activities', 2);
        $this->assertDatabaseCount('log_blocks', 1);
        $github = BrowsingActivity::where('domain', 'github.com')->firstOrFail();
        $this->assertNotNull($github->ended_at);
        $this->assertSame(120, $github->duration_seconds);

        Carbon::setTestNow('2026-08-17 10:06:00');
        $page = $this->actingAs($user)->get('/logs/2026-08-17')->assertOk()
            ->assertSee('data-timeline-browsing', false)
            ->assertSee('github.com')
            ->assertSee('ycombinator.com')
            ->assertSee('data-browsing-domain-list', false)
            ->assertDontSee('/en/rest?private=query');
        $this->assertNotNull(BrowsingActivity::where('domain', 'ycombinator.com')->firstOrFail()->ended_at);

        Carbon::setTestNow('2026-08-17 11:00:00');
        $send('https://mail.google.com/mail/u/0/')->assertCreated()->assertJsonPath('domain', 'google.com');
        $this->assertDatabaseCount('log_blocks', 2);
        $this->assertDatabaseHas('log_blocks', ['type' => 'sensor_browser']);
        Carbon::setTestNow();
    }

    public function test_browser_sensor_api_rejects_unknown_keys_and_extension_bundle_is_present(): void
    {
        $this->withHeader('X-CaptainsLog-Key', str_repeat('x', 64))->postJson(route('api.sensors.browser.activity'), [
            'url' => 'https://example.com',
            'client_id' => 'unknown-client',
        ])->assertUnauthorized();

        $manifestPath = public_path('captainslog-chrome-extension/manifest.json');
        $this->assertFileExists($manifestPath);
        $manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(3, $manifest['manifest_version']);
        $this->assertSame('service-worker.js', $manifest['background']['service_worker']);
        $this->assertFileExists(public_path('captainslog-chrome-extension/options.html'));
        $worker = file_get_contents(public_path('captainslog-chrome-extension/service-worker.js'));
        $this->assertStringContainsString('http://127.0.0.1:8016/', $worker);
        $this->assertStringContainsString('api/sensors/browser/activity', $worker);
        $this->assertStringContainsString('sensors/browser/pair/', $worker);
    }
}
