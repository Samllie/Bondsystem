<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeSplashTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_screen_requires_authentication(): void
    {
        $this->get(route('welcome'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_welcome_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('welcome'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('WelcomeSplash')
                ->where('userName', $user->name)
                ->has('redirectTo'));
    }
}
