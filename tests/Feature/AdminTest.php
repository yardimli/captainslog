<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\User;
use App\Services\GuestDemoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_admins_can_view_the_user_list_and_see_the_admin_menu(): void
    {
        $regular = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($regular)->get(route('admin.users'))->assertForbidden();
        $this->get(route('calendar'))->assertOk()->assertDontSee('href="'.route('admin.users').'"', false);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee($regular->email)
            ->assertSee($admin->email)
            ->assertSee('id="account-setup-tabs"', false)
            ->assertSee('href="'.route('settings.edit').'"', false)
            ->assertSee('href="'.route('sensors.index').'"', false)
            ->assertSee('href="'.route('api-usage.index').'"', false)
            ->assertSee('href="'.route('admin.users').'"', false)
            ->assertSee('Reset demo data')
            ->assertSee('data-confirm-demo-delete', false);
        $this->get(route('calendar'))->assertOk()
            ->assertSee('Account setup')
            ->assertDontSee('href="'.route('admin.users').'"', false);
    }

    public function test_admin_can_reset_demo_data_around_today_without_deleting_real_users(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        $admin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();
        $demo = User::factory()->create(['is_guest' => true]);
        DailyLog::create(['user_id' => $demo->id, 'log_date' => today()]);
        TaskDefinition::create(['user_id' => $demo->id, 'name' => 'Demo event']);

        $this->actingAs($regular)->delete(route('admin.demo-data.destroy'))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.demo-data.destroy'))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', 'Demo data reset around today.');

        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
        $this->assertDatabaseMissing('daily_logs', ['user_id' => $demo->id]);
        $this->assertDatabaseMissing('task_definitions', ['user_id' => $demo->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
        $this->assertDatabaseHas('users', ['id' => $regular->id, 'is_guest' => false]);
        $resetDemo = User::where('email', GuestDemoService::EMAIL)->firstOrFail();
        $this->assertDatabaseHas('daily_logs', ['user_id' => $resetDemo->id, 'log_date' => '2026-09-04 00:00:00']);
        $this->assertDatabaseHas('daily_logs', ['user_id' => $resetDemo->id, 'log_date' => '2026-08-28 00:00:00']);
    }
}
