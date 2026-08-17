<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionKeepAliveTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_keep_the_session_alive(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('session.keep-alive'));

        $response->assertNoContent()
            ->assertSessionHas('_keep_alive_at', fn ($value) => is_int($value));
    }

    public function test_guest_cannot_keep_a_session_alive(): void
    {
        $this->post(route('session.keep-alive'))
            ->assertRedirect(route('login'));
    }
}
