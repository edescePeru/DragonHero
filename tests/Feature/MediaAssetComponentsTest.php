<?php

namespace Tests\Feature;

use App\Domain\Inventory\Data\InventoryEntry;
use App\Domain\Inventory\InventoryService;
use App\Domain\Media\MediaAssetService;
use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\ZoneCatalogService;
use App\Models\Character;
use App\Models\Item;
use App\Models\Monster;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MediaAssetComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
        $this->seed(CharacterLevelRequirementSeeder::class);
    }

    private function media()
    {
        return $this->app->make(MediaAssetService::class);
    }

    private function player()
    {
        $user = User::factory()->create();
        return [$user, Character::factory()->selectedFor($user)->create()];
    }

    private function countMediaQueries($operation)
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (stripos($query->sql, 'media_assets') !== false) {
                $queries[] = $query->sql;
            }
        });
        $operation();
        return count($queries);
    }

    public function test_component_distinguishes_all_states_without_queries()
    {
        $monster = Monster::firstOrFail();
        $notLoadedQueries = $this->countMediaQueries(function () use ($monster) {
            $this->blade('<x-media.portrait :model="$monster" alt="Wolf" />', compact('monster'))->assertSee('data-media-state="not-loaded"', false);
        });

        $monster->setRelation('mediaAssets', collect());
        $emptyQueries = $this->countMediaQueries(function () use ($monster) {
            $this->blade('<x-media.portrait :model="$monster" alt="Wolf" />', compact('monster'))->assertSee('data-media-state="missing"', false);
        });

        $asset = $this->media()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'monsters/wolf.webp']);
        $monster->setRelation('mediaAssets', collect([$asset]));
        $loadedQueries = $this->countMediaQueries(function () use ($monster) {
            $this->blade('<x-media.portrait :model="$monster" alt="Wolf" />', compact('monster'))->assertSee('monsters/wolf.webp')->assertSee('data-media-state="loaded"', false);
        });

        $this->assertSame(0, $notLoadedQueries);
        $this->assertSame(0, $emptyQueries);
        $this->assertSame(0, $loadedQueries);
    }

    public function test_selection_is_deterministic()
    {
        $monster = Monster::firstOrFail();
        $late = $this->media()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'late.webp', 'sort_order' => 9]);
        $firstPrimary = $this->media()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'first.webp', 'sort_order' => 2, 'is_primary' => true]);
        $secondPrimary = $this->media()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'second.webp', 'sort_order' => 1, 'is_primary' => true]);
        $firstPrimary->update(['is_primary' => true]);
        $monster->setRelation('mediaAssets', collect([$late, $firstPrimary->fresh(), $secondPrimary]));

        $this->blade('<x-media.portrait :model="$monster" alt="Wolf" />', compact('monster'))->assertSee('second.webp')->assertDontSee('first.webp');
    }

    public function test_character_portrait_and_placeholder_render_without_media_queries_in_view()
    {
        list($user, $character) = $this->player();
        $this->actingAs($user)->get(route('characters.show', $character))->assertOk()->assertSee('data-media-state="missing"', false);
        $this->media()->attach($character, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'characters/hero.webp', 'is_primary' => true]);
        $this->actingAs($user)->get(route('characters.show', $character))->assertOk()->assertSee('characters/hero.webp')->assertSee('data-media-state="loaded"', false);
    }

    public function test_inventory_loads_icons_in_groups_and_missing_item_uses_placeholder()
    {
        list($user, $character) = $this->player();
        $items = Item::whereIn('code', ['worn_leather', 'wolf_fang'])->get();
        foreach ($items as $index => $item) {
            app(InventoryService::class)->addItem($character, $item, $index + 1);
            if ($index === 0) {
                $this->media()->attach($item, ['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => 'items/leather.webp']);
            }
        }
        $mediaQueries = $this->countMediaQueries(function () use ($user, $character) {
            $this->actingAs($user)->get(route('characters.inventory.index', $character))->assertOk()->assertSee('items/leather.webp')->assertSee('data-media-state="missing"', false);
        });
        $this->assertSame(1, $mediaQueries);

        $inventorySummary=['stackable_items'=>[['item_id'=>999999,'item_code'=>'missing_item','item_name'=>'Objeto ausente','quantity'=>3,'locked_quantity'=>0,'available_quantity'=>3,'max_stack'=>99,'used_slots'=>1]],'item_instances'=>[],'inventory_status'=>['effective_capacity'=>30,'current_used_slots'=>1,'current_free_slots'=>29],'pending_projection'=>['projected_used_slots'=>1,'projected_free_slots'=>29,'claim_fits'=>true,'missing_slots_for_claim'=>0,'effective_reserve'=>5,'hunting_can_continue'=>true]];
        $html = view('characters.inventory.index', ['character' => $character, 'inventorySummary' => $inventorySummary, 'inventoryItems' => collect()])->render();
        $this->assertStringContainsString('Objeto ausente', $html);
        $this->assertStringContainsString('data-media-state="not-loaded"', $html);
    }

    public function test_zone_media_loading_preserves_catalog_order_filters_and_weights()
    {
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $before = app(ZoneCatalogService::class)->zoneDetail($zone);
        $expected = $before->monsters->map(function ($monster) { return [$monster->id, $monster->pivot->weight, $monster->status]; })->all();
        $first = $before->monsters->first();
        $this->media()->attach($first, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'monsters/catalog.webp']);

        list($user) = $this->player();
        $mediaQueries = $this->countMediaQueries(function () use ($user, $zone) {
            $this->actingAs($user)->get(route('zones.show', $zone))->assertOk()->assertSee('monsters/catalog.webp');
        });
        $after = app(ZoneCatalogService::class)->zoneDetail($zone);
        $actual = $after->monsters->map(function ($monster) { return [$monster->id, $monster->pivot->weight, $monster->status]; })->all();
        $this->assertSame($expected, $actual);
        $this->assertSame(1, $mediaQueries);
    }
}
