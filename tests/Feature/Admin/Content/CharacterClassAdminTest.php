<?php

namespace Tests\Feature\Admin\Content;

use App\Domain\Characters\Templates\CharacterTemplateService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterTemplate;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterClassAdminTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['game_admin.emails' => ['classes-admin@example.test']]);
        $this->admin = User::factory()->create(['email' => 'classes-admin@example.test']);
    }

    private function payload(array $overrides = [])
    {
        return array_merge([
            'code' => 'warrior',
            'name' => 'Guerrero',
            'description' => 'Clase de combate cuerpo a cuerpo.',
            'status' => 'active',
            'sort_order' => 10,
            'can_dual_wield' => 1,
        ], $overrides);
    }

    public function test_access_is_protected_and_admin_can_list_classes()
    {
        $this->get(route('admin.content.character-classes.index'))->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get(route('admin.content.character-classes.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.content.character-classes.index'))->assertOk()->assertSee('Clases de personaje');
    }

    public function test_admin_creates_updates_code_and_dual_wield_even_with_dependencies()
    {
        $this->actingAs($this->admin)->post(route('admin.content.character-classes.store'), $this->payload())->assertRedirect();
        $class = CharacterClass::where('code', 'warrior')->firstOrFail();
        $this->assertTrue($class->can_dual_wield);
        Character::factory()->create(['character_class_id' => $class->id]);
        CharacterTemplate::factory()->create(['character_class_id' => $class->id]);
        $item = $this->item('class_dependency_item');
        $item->allowedCharacterClasses()->attach($class->id);

        $this->put(route('admin.content.character-classes.update', $class), $this->payload(['code' => 'vanguard', 'can_dual_wield' => 0]))->assertRedirect();
        $class->refresh();
        $this->assertSame('vanguard', $class->code);
        $this->assertFalse($class->can_dual_wield);
        $this->assertSame(1, $class->characters()->count());
        $this->assertSame(1, $class->characterTemplates()->count());
        $this->assertSame(1, $class->items()->count());
    }

    public function test_list_shows_icon_and_usage_counts()
    {
        Storage::fake('public');
        $class = CharacterClass::factory()->create(['name' => 'Arquero', 'can_dual_wield' => true, 'sort_order' => 22]);
        Character::factory()->create(['character_class_id' => $class->id]);
        CharacterTemplate::factory()->create(['character_class_id' => $class->id]);
        $item = $this->item('class_count_item');
        $item->allowedCharacterClasses()->attach($class->id);

        $this->actingAs($this->admin)->put(route('admin.content.character-classes.update', $class), $this->payload([
            'code' => $class->code, 'name' => $class->name, 'sort_order' => 22,
            'icon' => UploadedFile::fake()->image('archer.png', 128, 128),
        ]))->assertRedirect();

        $this->assertDatabaseHas('media_assets', ['mediable_type' => 'character_class', 'mediable_id' => $class->id, 'asset_type' => MediaAssetType::ICON, 'is_primary' => 1]);
        $this->actingAs($this->admin)->get(route('admin.content.character-classes.index'))
            ->assertOk()->assertSee('Arquero')->assertSee('Dual wield')->assertSee('22');
    }

    public function test_last_active_class_cannot_be_deactivated_or_hidden()
    {
        $only = CharacterClass::where('status', 'active')->firstOrFail();
        $this->actingAs($this->admin)->patch(route('admin.content.character-classes.deactivate', $only))->assertSessionHasErrors('content');
        $this->assertSame('active', $only->fresh()->status);
        $this->patch(route('admin.content.character-classes.hide', $only))->assertSessionHasErrors('content');
        $this->assertSame('active', $only->fresh()->status);

        CharacterClass::factory()->create(['status' => 'active']);
        $this->patch(route('admin.content.character-classes.deactivate', $only))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $only->fresh()->status);
    }

    public function test_validation_rejects_duplicate_and_invalid_codes()
    {
        CharacterClass::factory()->create(['code' => 'mage']);
        $this->actingAs($this->admin)->post(route('admin.content.character-classes.store'), $this->payload(['code' => 'mage']))->assertSessionHasErrors('code');
        $this->post(route('admin.content.character-classes.store'), $this->payload(['code' => 'Mago inválido']))->assertSessionHasErrors('code');
    }

    public function test_used_template_cannot_change_character_class()
    {
        $original = CharacterClass::where('status', 'active')->firstOrFail();
        $other = CharacterClass::factory()->create();
        $template = CharacterTemplate::factory()->create(['character_class_id' => $original->id, 'status' => 'inactive']);
        Character::factory()->create(['character_class_id' => $original->id, 'character_template_id' => $template->id]);
        $data = $template->only(['code','name','description','presentation_gender','body_type','status','sort_order','base_max_health','base_attack','base_defense','base_accuracy','base_evasion','base_critical_rate']);
        $data['character_class_id'] = $other->id;

        try {
            app(CharacterTemplateService::class)->save($data, $template);
            $this->fail('Se permitió cambiar la clase de una plantilla utilizada.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('clase no puede cambiar', $e->getMessage());
        }
        $this->assertSame($original->id, $template->fresh()->character_class_id);
    }

    private function item($code)
    {
        return Item::create([
            'code' => $code,
            'name' => $code,
            'item_type' => 'equipment',
            'equipment_type' => 'weapon',
            'hand_requirement' => 'one_hand',
            'equipment_family' => 'sword',
            'required_level' => 1,
            'rarity' => 'common',
            'is_stackable' => false,
            'max_stack' => 1,
            'status' => 'active',
        ]);
    }
}
