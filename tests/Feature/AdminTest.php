<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\TaskDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_the_user_list_and_see_the_admin_menu(): void
    {
        $regular = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($regular)->get(route('admin.users'))->assertForbidden();
        $this->get(route('calendar'))->assertOk()->assertDontSee('href="'.route('admin.users').'"', false);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee($regular->email)
            ->assertSee($admin->email)
            ->assertSee('Delete all demo data')
            ->assertSee('data-confirm-demo-delete', false);
        $this->get(route('calendar'))->assertOk()->assertSee('href="'.route('admin.users').'"', false);
    }

    public function test_admin_can_delete_demo_users_and_their_data_without_deleting_real_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $regular = User::factory()->create();
        $demo = User::factory()->create(['is_guest' => true]);
        DailyLog::create(['user_id' => $demo->id, 'log_date' => today()]);
        TaskDefinition::create(['user_id' => $demo->id, 'name' => 'Demo event']);

        $this->actingAs($regular)->delete(route('admin.demo-data.destroy'))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.demo-data.destroy'))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('status', 'Deleted 1 demo user and all associated demo data.');

        $this->assertDatabaseMissing('users', ['id' => $demo->id]);
        $this->assertDatabaseMissing('daily_logs', ['user_id' => $demo->id]);
        $this->assertDatabaseMissing('task_definitions', ['user_id' => $demo->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => true]);
        $this->assertDatabaseHas('users', ['id' => $regular->id, 'is_guest' => false]);
    }
}
