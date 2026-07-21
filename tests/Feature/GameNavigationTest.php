<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use App\Models\World;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CharacterLevelRequirementSeeder::class);
    }

    private function player()
    {
        $user = User::factory()->create();
        $character = Character::factory()->selectedFor($user)->create();

        return [$user, $character];
    }

    private function assertGlobal($response)
    {
        return $response->assertSee('Inicio')
            ->assertSee('Mi personaje')
            ->assertSee('Mundo')
            ->assertSee('Mapas')
            ->assertSee(route('world-maps.index'), false)
            ->assertSee('Cerrar sesión')
            ->assertDontSee('Add Product')
            ->assertDontSee('Reports')
            ->assertDontSee('404 Error');
    }

    public function test_global_and_contextual_navigation_states()
    {
        list($user, $character) = $this->player();
        $this->actingAs($user);
        $this->assertGlobal($this->get(route('dashboard')))->assertOk();
        $this->assertGlobal($this->get(route('characters.show', $character)))
            ->assertSee('nav-link active" href="'.route('characters.overview', $character), false)
            ->assertDontSee('nav-link active" href="'.route('characters.show', $character).'"', false)
            ->assertSee('Resumen y estadísticas');
        $this->assertGlobal($this->get(route('characters.inventory.index', $character)))
            ->assertSee('nav-link active" href="'.route('characters.inventory.index', $character), false)
            ->assertSee('Volver al personaje');
        $this->assertGlobal($this->get(route('characters.wallet.show', $character)))
            ->assertSee('nav-link active" href="'.route('characters.wallet.show', $character), false)
            ->assertSee('Volver al personaje');
    }

    public function test_world_navigation_and_breadcrumbs()
    {
        list($user) = $this->player();
        $this->seed(WorldCatalogSeeder::class);
        $world = World::where('code', 'eldoria')->firstOrFail();
        $region = $world->regions()->firstOrFail();
        $zone = $region->zones()->firstOrFail();
        $response = $this->actingAs($user)->get(route('zones.show', $zone));
        $this->assertGlobal($response)->assertOk()
            ->assertSeeInOrder(['Inicio', 'Mundo', $world->name, $region->name, $zone->name])
            ->assertDontSee('Resumen y estadísticas');
    }

    public function test_links_never_use_another_character()
    {
        list($user, $character) = $this->player();
        $other = Character::factory()->selected()->for(User::factory())->create();
        $this->actingAs($user)->get(route('characters.show', $character))
            ->assertSee(route('characters.show', $character), false)
            ->assertDontSee(route('characters.show', $other), false);
        $this->get(route('characters.show', $other))->assertForbidden();
    }

    public function test_guest_remains_blocked()
    {
        list(, $character) = $this->player();
        $this->get(route('characters.inventory.index', $character))->assertRedirect('/login');
    }

    public function test_sidebar_javascript_does_not_override_server_route_state()
    {
        $source = file_get_contents(base_path('src/assets/js/sidebar.js'));

        $this->assertStringNotContainsString('window.location.pathname', $source);
        $this->assertStringNotContainsString("classList.remove('active')", $source);
    }
}
