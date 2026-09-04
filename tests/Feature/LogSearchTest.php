<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\MobileBrowsingVisit;
use App\Models\TaskDefinition;
use App\Models\TaskEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_search_opens_owned_log_results_with_date_details_and_open_action(): void
    {
        $user = User::factory()->create(['time_format' => '12']);
        $other = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $log->blocks()->create(['type' => 'text', 'content' => 'Finished the lighthouse report', 'occurred_at' => '2026-08-15 14:30:00']);
        DailyLog::create(['user_id' => $other->id, 'log_date' => '2026-08-16'])
            ->blocks()->create(['type' => 'text', 'content' => 'Private lighthouse note']);

        $this->actingAs($user)->get(route('calendar'))->assertOk()
            ->assertSee('aria-label="Search logs"', false)
            ->assertSee('href="'.route('search.index').'"', false);

        $this->get(route('search.index', ['q' => 'lighthouse']))->assertOk()
            ->assertSee('1 result for')
            ->assertSee('Finished the lighthouse report')
            ->assertSee('Saturday, August 15, 2026')
            ->assertSee('2:30 PM', false)
            ->assertSee('data-search-result-open', false)
            ->assertSee('data-search-result-readonly', false)
            ->assertSee('aria-readonly="true"', false)
            ->assertSee('Open date')
            ->assertSee('href="'.route('logs.show', '2026-08-15').'"', false)
            ->assertDontSee('Private lighthouse note');
    }

    public function test_search_matches_event_values_and_sensor_metadata(): void
    {
        $user = User::factory()->create(['time_format' => '24']);
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $task = TaskDefinition::create(['user_id' => $user->id, 'name' => 'Mood', 'recurrence_type' => 'daily']);
        $eventBlock = $log->blocks()->create(['type' => 'event', 'occurred_at' => '2026-08-15 09:00:00']);
        TaskEvent::create(['daily_log_id' => $log->id, 'task_definition_id' => $task->id, 'log_block_id' => $eventBlock->id, 'task_name' => 'Mood', 'selected_value' => 'Focused', 'occurred_at' => '2026-08-15 09:00:00']);
        $log->blocks()->create(['type' => 'sensor_github', 'content' => 'totallog', 'metadata' => ['commits' => [['sha' => 'abc', 'message' => 'Add searchable widgets']]]]);

        $this->actingAs($user)->get(route('search.index', ['q' => 'Focused']))->assertOk()->assertSee('Mood')->assertSee('Focused');
        $this->get(route('search.index', ['q' => 'searchable widgets']))->assertOk()->assertSee('totallog')->assertSee('1 result for');
    }

    public function test_ajax_search_starts_at_two_letters_and_returns_replaceable_results_markup(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $log->blocks()->create(['type' => 'text', 'content' => 'Finished the lighthouse report']);

        $response = $this->actingAs($user)->getJson(route('search.index', ['q' => 'li']));

        $response->assertOk()
            ->assertJsonPath('url', route('search.index', ['q' => 'li']));
        $this->assertStringContainsString('Finished the lighthouse report', $response->json('html'));
        $this->assertStringContainsString('data-search-result-open', $response->json('html'));

        $shortResponse = $this->getJson(route('search.index', ['q' => 'l']));
        $shortResponse->assertOk();
        $this->assertStringContainsString('Type at least two letters', $shortResponse->json('html'));
        $this->assertStringNotContainsString('Finished the lighthouse report', $shortResponse->json('html'));
    }

    public function test_search_javascript_debounces_requests_and_opens_read_only_result_panel(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('keyword.length < 2', $javascript);
        $this->assertStringContainsString('window.setTimeout(search, 500)', $javascript);
        $this->assertStringContainsString('new AbortController()', $javascript);
        $this->assertStringContainsString('openSearchResult(result)', $javascript);
        $this->assertStringContainsString("openOverlay('search-result')", $javascript);
    }

    public function test_mobile_browsing_search_results_include_grouped_domain_details_for_the_panel(): void
    {
        $user = User::factory()->create();
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-08-15']);
        $block = $log->blocks()->create([
            'type' => 'sensor_mobile_browser',
            'content' => '11 domains · 44 visits',
            'occurred_at' => '2026-08-15 09:00:00',
        ]);

        foreach (['first', 'second'] as $key) {
            MobileBrowsingVisit::create([
                'user_id' => $user->id,
                'daily_log_id' => $log->id,
                'log_block_id' => $block->id,
                'domain' => 'example.com',
                'visit_key' => hash('sha256', $key),
                'visited_at' => $key === 'first' ? '2026-08-15 09:01:00' : '2026-08-15 09:02:00',
            ]);
        }
        MobileBrowsingVisit::create([
            'user_id' => $user->id,
            'daily_log_id' => $log->id,
            'log_block_id' => $block->id,
            'domain' => 'laravel.com',
            'visit_key' => hash('sha256', 'third'),
            'visited_at' => '2026-08-15 09:03:00',
        ]);

        $this->actingAs($user)->get(route('search.index', ['q' => 'example']))
            ->assertOk()
            ->assertSee('data-search-result-details', false)
            ->assertSee('"kind":"browsing"', false)
            ->assertSee('"mode":"visits"', false)
            ->assertSee('"domain":"example.com","visits":2', false)
            ->assertSee('"domain":"laravel.com","visits":1', false);
    }
}
