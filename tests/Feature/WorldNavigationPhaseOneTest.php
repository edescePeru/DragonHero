<?php

namespace Tests\Feature;

use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\Region;
use App\Models\User;
use App\Models\World;
use App\Models\WorldMap;
use App\Models\WorldMapArea;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorldNavigationPhaseOneTest extends TestCase
{
    use RefreshDatabase;

    private $character;
    private $world;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
        Storage::fake('public');
        Storage::disk('public')->put('maps/navigation.png', 'image');
        $this->world = World::where('code', 'eldoria')->firstOrFail();
        $this->character = Character::factory()->selected()->for(User::factory())->create();
    }

    private function region($code, $name, $sortOrder = 0, $status = 'active', World $world = null)
    {
        return Region::create([
            'world_id' => ($world ?: $this->world)->id,
            'code' => $code,
            'name' => $name,
            'recommended_level_min' => 1,
            'status' => $status,
            'sort_order' => $sortOrder,
        ]);
    }

    private function map(Region $region, $name, $status = 'active', $default = true)
    {
        return WorldMap::create([
            'world_id' => null,
            'region_id' => $region->id,
            'code' => 'map_'.strtolower(str_replace(' ', '_', $name)).'_'.uniqid(),
            'name' => $name,
            'image_disk' => 'public',
            'image_path' => 'maps/navigation.png',
            'original_width' => 640,
            'original_height' => 360,
            'mime_type' => 'image/png',
            'file_size' => 5,
            'version' => 1,
            'status' => $status,
            'is_default' => $default,
            'sort_order' => 0,
        ]);
    }

    public function test_world_index_is_visual_ordered_active_and_has_image_fallback()
    {
        $first = World::create(['code' => 'a_world', 'name' => 'A World', 'description' => 'First', 'status' => 'active', 'sort_order' => 0]);
        World::create(['code' => 'hidden_world', 'name' => 'Hidden World', 'status' => 'inactive', 'sort_order' => 0]);
        Storage::disk('public')->put('worlds/a.webp', 'image');
        $first->mediaAssets()->create(['asset_type' => MediaAssetType::IMAGE, 'disk' => 'public', 'path' => 'worlds/a.webp', 'is_primary' => true, 'sort_order' => 0]);

        $response = $this->actingAs($this->character->user)->get(route('worlds.index'));

        $response->assertOk()->assertSeeInOrder(['A World', 'Eldoria'])->assertSee('/storage/worlds/a.webp', false)->assertSee('Vista no disponible')->assertSee('Explorar mundo')->assertSee(route('worlds.show', $first), false)->assertDontSee('Hidden World');
    }

    public function test_world_opens_first_active_region_and_selector_is_scoped_ordered_and_resolvable()
    {
        $existing = $this->world->regions()->firstOrFail();
        $existing->update(['sort_order' => 20]);
        $first = $this->region('first_region', 'Primera Región', 10);
        $inactive = $this->region('inactive_region', 'Región Inactiva', 1, 'inactive');
        $withoutMap = $this->region('without_map', 'Sin mapa', 30);
        $firstMap = $this->map($first, 'Mapa inicial');
        $existingMap = $this->map($existing, 'Mapa Valtheria');
        $otherWorld = World::create(['code' => 'other', 'name' => 'Otro mundo', 'status' => 'active', 'sort_order' => 2]);
        $foreign = $this->region('foreign', 'Región extranjera', 0, 'active', $otherWorld);
        $this->map($foreign, 'Mapa extranjero');

        $response = $this->actingAs($this->character->user)->get(route('worlds.show', $this->world));
        $response->assertOk()->assertSee('Mapa inicial')->assertSee('Primera Región')->assertSee(route('worlds.regions.show', [$this->world, $existing]), false)->assertDontSee('Sin mapa')->assertDontSee('Región Inactiva')->assertDontSee('Región extranjera');

        $this->get(route('worlds.regions.show', [$this->world, $existing]))
            ->assertOk()
            ->assertSee('Mapa Valtheria')
            ->assertViewHas('worldMap', function (array $worldMap) use ($existing) {
                return (int) $worldMap['navigation']['region']->id === (int) $existing->id;
            });
        $this->get(route('worlds.regions.show', [$this->world, $foreign]))->assertNotFound();
        $this->assertSame($firstMap->id, WorldMap::where('region_id', $first->id)->firstOrFail()->id);
        $this->assertSame($existingMap->id, WorldMap::where('region_id', $existing->id)->firstOrFail()->id);
        $this->assertNotNull($inactive);
        $this->assertNotNull($withoutMap);
    }

    public function test_missing_region_or_map_has_controlled_fallback_and_inactive_context_is_rejected()
    {
        $empty = World::create(['code' => 'empty', 'name' => 'Vacío', 'status' => 'active', 'sort_order' => 3]);
        $this->actingAs($this->character->user)->get(route('worlds.show', $empty))->assertOk()->assertSee('todavía no tiene regiones disponibles');
        $this->get(route('worlds.show', $this->world))->assertOk()->assertSee('Mapa no disponible');

        $inactiveWorld = World::create(['code' => 'inactive', 'name' => 'Inactivo', 'status' => 'inactive', 'sort_order' => 4]);
        $this->get(route('worlds.show', $inactiveWorld))->assertNotFound();
        $inactiveRegion = $this->region('disabled', 'Deshabilitada', 0, 'inactive');
        $this->get(route('worlds.regions.show', [$this->world, $inactiveRegion]))->assertNotFound();
        $activeRegion = $this->world->regions()->where('status', 'active')->firstOrFail();
        $this->map($activeRegion, 'Mapa inactivo', 'inactive');
        $this->get(route('worlds.regions.show', [$this->world, $activeRegion]))->assertOk()->assertSee('Mapa no disponible');
    }

    public function test_contextual_renderer_preserves_polygon_zone_actions_and_legacy_routes()
    {
        $region = $this->world->regions()->firstOrFail();
        $map = $this->map($region, 'Mapa regional');
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $points = ['coordinate_system' => 'normalized', 'points' => [['x' => 0.1, 'y' => 0.1], ['x' => 0.4, 'y' => 0.1], ['x' => 0.2, 'y' => 0.4]]];
        $area = WorldMapArea::create(['world_map_id' => $map->id, 'code' => 'forest', 'name' => 'Bosque', 'polygon_points' => $points, 'action_type' => 'zone', 'zone_id' => $zone->id, 'display_mode' => 'panel', 'z_index' => 0, 'status' => 'active', 'sort_order' => 0, 'version' => 1]);

        $this->actingAs($this->character->user)->get(route('worlds.show', $this->world))->assertOk()->assertSee('points="64,36 256,36 128,144"', false)->assertSee('automatic_hunting_url')->assertSee('manual_combat_url');
        $this->assertEquals($points, $area->fresh()->polygon_points);
        $this->get(route('world-maps.index'))->assertOk();
        $this->get(route('world-maps.show', $map))->assertOk();
        $this->followingRedirects()->get(route('world-maps.region', $region))->assertOk();
        $this->get(route('regions.show', $region))->assertOk();
    }
}
