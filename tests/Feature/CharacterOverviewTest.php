<?php

namespace Tests\Feature;

use App\Domain\Equipment\CharacterEquipmentService;
use App\Domain\Equipment\CharacterEquipmentSlot;
use App\Domain\Inventory\Instances\ItemInstanceStatus;
use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\User;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CharacterOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CharacterLevelRequirementSeeder::class);
    }

    public function test_overview_requires_authentication_and_ownership()
    {
        $character = Character::factory()->selected()->create();

        $this->get(route('characters.overview', $character))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('characters.overview', $character))->assertForbidden();
        $this->actingAs($character->user)->get(route('characters.overview', $character))->assertOk();
    }

    public function test_overview_uses_template_base_visual_and_legacy_vertical_fallback_not_portrait()
    {
        Storage::fake('public');
        $class = \App\Models\CharacterClass::where('code', 'adventurer')->firstOrFail();
        $template = \App\Models\CharacterTemplate::factory()->create(['character_class_id'=>$class->id]);
        $variants = ['128'=>'body/128.webp','256'=>'body/256.webp','512'=>'body/512.webp'];
        foreach ($variants as $path) Storage::disk('public')->put($path, 'webp');
        $template->mediaAssets()->create(['asset_type'=>'base_visual','disk'=>'public','path'=>$variants['256'],'mime_type'=>'image/webp','metadata'=>['character_visual_version'=>1,'root'=>'body','body_type'=>$template->body_type,'canvas'=>['width'=>512,'height'=>768,'ratio'=>'2:3'],'variants'=>$variants],'is_primary'=>true]);
        $character = Character::factory()->selected()->fromTemplate($template)->create();
        $character->mediaAssets()->create(['asset_type'=>'portrait','disk'=>'public','path'=>'portrait.webp','is_primary'=>true]);
        $this->actingAs($character->user)->get(route('characters.overview',$character))->assertOk()->assertSee(Storage::disk('public')->url('body/256.webp'),false)->assertSee('width="256" height="384"',false);
        $legacy = Character::factory()->selected()->create(['character_template_id'=>null]);
        $this->actingAs($legacy->user)->get(route('characters.overview',$legacy))->assertOk()->assertSee('default-256.svg',false);
    }

    public function test_overview_renders_authoritative_summary_capacity_and_accessible_actions()
    {
        $character = Character::factory()->selected()->create(['level' => 2, 'experience' => 140]);
        $material = $this->stackableItem('overview_material', 'Mineral de prueba', 5);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $material->id, 'quantity' => 12, 'locked_quantity' => 2]);
        $weapon = $this->uniqueItem('overview_weapon', 'Espada de prueba');
        $instance = $this->createItemInstance($character, $weapon);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));

        $response->assertOk()
            ->assertSee('Vista compacta')
            ->assertSee('EXP 140')
            ->assertSee('Faltan 110')
            ->assertSee('Usados 4 de 30')
            ->assertSee('Mineral de prueba')
            ->assertSee('×5')
            ->assertSee('×2')
            ->assertSee('Espada de prueba')
            ->assertSee('data-overview-open-panel', false)
            ->assertSee(route('characters.equipment.equip', $character), false)
            ->assertSee('Estadísticas efectivas')
            ->assertDontSee(route('characters.item-instances.refine', [$character, $instance]), false);
    }

    public function test_equipped_item_uses_existing_unequip_endpoint_and_all_eight_slots_are_present()
    {
        $character = Character::factory()->selected()->create();
        $weapon = $this->uniqueItem('equipped_overview', 'Arma equipada');
        $instance = $this->createItemInstance($character, $weapon);
        app(CharacterEquipmentService::class)->equip($character, $instance->uuid, CharacterEquipmentSlot::MAIN_HAND);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));

        $response->assertOk()
            ->assertSee('Arma equipada +0')
            ->assertSee(route('characters.equipment.unequip', $character), false);
        foreach (CharacterEquipmentSlot::all() as $slot) {
            $response->assertSee(CharacterEquipmentSlot::label($slot));
        }
    }

    public function test_item_media_loading_is_bounded_instead_of_one_query_per_item()
    {
        $character = Character::factory()->selected()->create();
        for ($index = 1; $index <= 8; $index++) {
            $item = $this->stackableItem('bounded_'.$index, 'Material '.$index, 99);
            CharacterItem::create(['character_id' => $character->id, 'item_id' => $item->id, 'quantity' => 1, 'locked_quantity' => 0]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($character->user)->get(route('characters.overview', $character))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // La composición añade una consulta acotada para asociaciones visuales, nunca una por Item.
        $this->assertLessThanOrEqual(26, $queryCount, 'La vista compacta ejecutó demasiadas consultas: '.$queryCount);
    }

    public function test_visual_inventory_expands_aggregated_quantity_without_changing_textual_summary()
    {
        $character = Character::factory()->selected()->create();
        $material = $this->stackableItem('worn_leather_visual', 'Cuero desgastado', 99);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $material->id, 'quantity' => 103, 'locked_quantity' => 10]);

        $overview = $this->actingAs($character->user)->get(route('characters.overview', $character));
        $overview->assertOk()->assertSee('×99')->assertSee('×4')->assertDontSee('×103');

        $inventory = $this->get(route('characters.inventory.index', $character));
        $inventory->assertOk()->assertSee('<td>99</td><td>2</td><td>103</td><td>10</td><td><strong>93</strong>', false);
    }

    public function test_three_unique_instances_render_as_three_independent_slots()
    {
        $character = Character::factory()->selected()->create();
        $item = $this->uniqueItem('three_unique_slots', 'Espada individual');
        for ($unit = 1; $unit <= 3; $unit++) $this->createItemInstance($character, $item);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));
        $this->assertSame(3, substr_count($response->getContent(), 'Ver detalles de Espada individual'));
    }

    public function test_frontend_contract_is_responsive_and_uses_safe_dom_updates()
    {
        $styles = file_get_contents(base_path('src/assets/scss/_character-overview.scss'));
        $script = file_get_contents(base_path('src/assets/js/character-overview.js'));

        $this->assertStringContainsString('repeat(10', $styles);
        $this->assertStringContainsString('@media (max-width: 1199.98px)', $styles);
        $this->assertStringContainsString('@media (max-width: 767.98px)', $styles);
        $this->assertStringContainsString('@media (max-width: 399.98px)', $styles);
        $this->assertStringContainsString('replaceChildren', $script);
        $this->assertStringContainsString("Accept: 'application/json'", $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    public function test_overview_uses_item_variant_64_and_detail_variant_128_with_fallback()
    {
        Storage::fake('public');
        $character = Character::factory()->selected()->create();
        $item = $this->stackableItem('overview_variants', 'Objeto con variantes', 20);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $item->id, 'quantity' => 2, 'locked_quantity' => 0]);
        $path = tempnam(sys_get_temp_dir(), 'overview_image_');
        $image = imagecreatetruecolor(48, 80);
        imagefill($image, 0, 0, imagecolorallocate($image, 40, 120, 200));
        imagepng($image, $path);
        imagedestroy($image);
        $asset = app(CatalogImageService::class)->replace($item, CatalogImageType::ITEM, new UploadedFile($path, 'item.png', 'image/png', null, true));
        unlink($path);

        $response = $this->actingAs($character->user)->get(route('characters.overview', $character));
        $response->assertOk()
            ->assertSee(Storage::disk('public')->url($asset->metadata['variants']['64']), false)
            ->assertSee(Storage::disk('public')->url($asset->metadata['variants']['128']), false)
            ->assertSee('width="64"', false)
            ->assertSee('width="128"', false);

        $fallbackItem = $this->stackableItem('overview_fallback', 'Objeto sin imagen', 20);
        CharacterItem::create(['character_id' => $character->id, 'item_id' => $fallbackItem->id, 'quantity' => 1, 'locked_quantity' => 0]);
        $this->get(route('characters.overview', $character))->assertSee('default-item-64.webp', false);
    }

    private function stackableItem($code, $name, $maxStack)
    {
        return Item::create(['code' => $code, 'name' => $name, 'item_type' => 'material', 'equipment_type' => null, 'rarity' => 'common', 'is_stackable' => true, 'max_stack' => $maxStack, 'status' => 'active']);
    }

    private function uniqueItem($code, $name)
    {
        return Item::create(['code' => $code, 'name' => $name, 'item_type' => 'equipment', 'equipment_type' => 'weapon', 'rarity' => 'common', 'is_stackable' => false, 'max_stack' => 1, 'required_level' => 1, 'status' => 'active']);
    }

    private function createItemInstance(Character $character, Item $item)
    {
        return ItemInstance::create(['uuid' => (string) Str::uuid(), 'character_id' => $character->id, 'item_id' => $item->id, 'refinement_level' => 0, 'status' => ItemInstanceStatus::AVAILABLE, 'origin_type' => 'legacy_inventory', 'origin_id' => random_int(10000, 99999), 'origin_unit_index' => 1, 'acquired_at' => now()]);
    }
}
