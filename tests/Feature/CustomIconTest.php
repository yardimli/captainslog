<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Goal;
use App\Models\LogBlock;
use App\Models\TaskDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_icons_are_cropped_validated_and_copied_to_recorded_entries(): void
    {
        $user = User::factory()->create();
        $icon = $this->pngDataUri();

        $response = $this->actingAs($user)->postJson(route('tasks.store'), [
            'name' => 'Photo event',
            'emoji' => '✅',
            'icon_data' => $icon,
            'color' => '#4f46e5',
            'recurrence_type' => 'daily',
        ])->assertCreated();

        $task = TaskDefinition::findOrFail($response->json('event.id'));
        $this->assertSame($icon, $task->icon_data);
        $log = DailyLog::create(['user_id' => $user->id, 'log_date' => '2026-09-04']);
        $this->postJson(route('events.store', [$log, $task]))
            ->assertCreated()
            ->assertJsonPath('icon_data', $icon);
        $this->assertSame($icon, $log->blocks()->sole()->icon_data);

        $this->get(route('logs.show', '2026-09-04'))->assertOk()->assertSee($icon, false);
        $this->patchJson(route('tasks.update', $task), [
            'name' => 'Photo event',
            'emoji' => '✅',
            'remove_icon' => '1',
            'color' => '#4f46e5',
            'recurrence_type' => 'daily',
        ])->assertOk();
        $this->assertNull($task->fresh()->icon_data);
        $this->assertSame($icon, $log->blocks()->sole()->icon_data);

        $this->post(route('tasks.store'), [
            'name' => 'Wrong crop',
            'icon_data' => $this->pngDataUri(64),
            'color' => '#4f46e5',
            'recurrence_type' => 'daily',
        ])->assertSessionHasErrors('icon_data');
    }

    public function test_goal_icons_render_in_setup_calendar_details_and_manual_day_events(): void
    {
        $user = User::factory()->create();
        $icon = $this->pngDataUri();

        $this->actingAs($user)->post(route('goals.store'), [
            'name' => 'Picture goal',
            'emoji' => '🎯',
            'icon_data' => $icon,
            'color' => '#4f46e5',
            'target_points' => 5,
            'period' => 'daily',
            'manual_enabled' => '1',
        ])->assertRedirect(route('goals.index'));

        $goal = Goal::firstOrFail();
        $this->get(route('goals.index'))->assertOk()
            ->assertSee('data-icon-upload', false)
            ->assertSee('data-icon-crop-dialog', false)
            ->assertSee('data-icon-crop-stage', false)
            ->assertSee($icon, false);
        $this->get(route('calendar', '2026-09-04'))->assertOk()->assertSee($icon, false);
        $this->get(route('goals.show', ['goal' => $goal, 'date' => '2026-09-04']))->assertOk()->assertSee($icon, false);

        $this->post(route('goals.entries.store', $goal), [
            'points' => 2,
            'note' => 'Custom icon progress',
            'occurred_on' => '2026-09-04',
        ])->assertRedirect();
        $this->assertSame($icon, LogBlock::where('type', 'event')->sole()->icon_data);
        $this->get(route('logs.show', '2026-09-04'))->assertOk()->assertSee($icon, false);

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("stage?.addEventListener('pointermove'", $script);
        $this->assertStringContainsString("canvas.toDataURL('image/png')", $script);
        $this->assertStringContainsString('canvas.width = targetSize', $script);
    }

    private function pngDataUri(int $size = 128): string
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $rows = str_repeat("\0".str_repeat("\x4f\x46\xe5\xff", $size), $size);
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 6, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows))
            .$chunk('IEND', '');

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
