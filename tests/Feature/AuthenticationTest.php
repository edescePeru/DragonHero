<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_displayed()
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('auth-login');
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => Hash::make('ClaveSegura123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'ClaveSegura123',
        ]);

        $response->assertRedirect(route('characters.create'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => Hash::make('ClaveSegura123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_register()
    {
        $response = $this->post('/registro', [
            'name' => 'Heroe de Prueba',
            'email' => 'heroe@example.test',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('characters.create'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'name' => 'Heroe de Prueba',
            'email' => 'heroe@example.test',
        ]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_cannot_access_dashboard()
    {
        $this->get('/')
            ->assertRedirect('/login');
    }
}
