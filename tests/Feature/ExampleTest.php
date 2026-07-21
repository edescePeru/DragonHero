<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guest_is_redirected_to_login_from_dashboard()
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create();
        Character::factory()->selectedFor($user)->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
    }
}
