<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['game_admin.emails' => ['admin@example.test']]);
    }

    public function test_authorized_user_sees_content_admin_link()
    {
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        Character::factory()->selectedFor($admin)->create();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Administrar contenido')
            ->assertSee(route('admin.content.items.index'));
    }

    public function test_normal_user_does_not_see_link_and_direct_access_remains_forbidden()
    {
        $player = User::factory()->create(['email' => 'player@example.test']);
        Character::factory()->selectedFor($player)->create();

        $this->actingAs($player)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Administrar contenido');

        $this->get(route('admin.content.items.index'))->assertForbidden();
    }
}
