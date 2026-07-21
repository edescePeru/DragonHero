<?php

namespace Tests\Feature;

use App\Domain\Characters\Actions\CreateCharacterAction;
use App\Domain\Characters\CharacterStatus;
use App\Models\Character;
use App\Models\User;
use App\Models\CharacterTemplate;
use App\Domain\Media\MediaAssetType;
use App\Policies\CharacterPolicy;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class CharacterTest extends TestCase
{
    use RefreshDatabase;
    private $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CharacterLevelRequirementSeeder::class);
        Storage::fake('public');
        $class = \App\Models\CharacterClass::where('code', 'adventurer')->firstOrFail();
        $this->template = CharacterTemplate::factory()->create(['character_class_id' => $class->id, 'status' => 'active']);
        $variants = [];
        foreach ([128, 256, 512] as $size) { $variants[(string) $size] = 'templates/'.$size.'.webp'; Storage::disk('public')->put($variants[(string) $size], 'webp'); }
        $this->template->mediaAssets()->create(['asset_type'=>MediaAssetType::BASE_VISUAL,'disk'=>'public','path'=>$variants['256'],'mime_type'=>'image/webp','width'=>256,'height'=>384,'file_size'=>4,'metadata'=>['character_visual_version'=>1,'root'=>'templates','body_type'=>$this->template->body_type,'canvas'=>['width'=>512,'height'=>768,'ratio'=>'2:3'],'variants'=>$variants],'is_primary'=>true,'sort_order'=>0]);
    }

    public function test_authenticated_user_without_character_sees_creation_screen()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect(route('characters.create'));
        $this->actingAs($user)->get('/characters/create')->assertOk()->assertViewIs('characters.create');
    }

    public function test_guest_cannot_access_character_routes()
    {
        $this->get('/characters/create')->assertRedirect('/login');
        $this->post('/characters', ['name' => 'Invitado'])->assertRedirect('/login');
    }

    public function test_user_can_create_first_character_with_normalized_name()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/characters', ['name' => '  Sir   Dragon  ', 'template_id'=>$this->template->id]);
        $character = Character::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('dashboard'));
        $this->assertSame('Sir Dragon', $character->name);
        $this->assertSame('sir dragon', $character->normalized_name);
        $this->assertSame($character->id, $user->fresh()->active_character_id);
    }

    public function test_initial_stats_cannot_be_manipulated_from_request()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/characters', [
            'name' => 'ServidorSeguro', 'level' => 999, 'experience' => 999999,
            'template_id' => $this->template->id,
            'current_health' => 9999, 'base_max_health' => 9999, 'base_attack' => 9999,
            'base_defense' => 9999, 'base_accuracy' => 9999, 'base_evasion' => 9999,
            'base_critical_rate' => 99.99, 'status' => CharacterStatus::BLOCKED,
        ])->assertRedirect();
        $character = Character::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $character->level);
        $this->assertSame(0, $character->experience);
        $this->assertSame(100, $character->current_health);
        $this->assertSame(100, $character->base_max_health);
        $this->assertSame(10, $character->base_attack);
        $this->assertSame(5, $character->base_defense);
        $this->assertSame(80, $character->base_accuracy);
        $this->assertSame(5, $character->base_evasion);
        $this->assertSame('5.00', $character->base_critical_rate);
        $this->assertSame(CharacterStatus::ACTIVE, $character->status);
    }

    public function test_user_can_create_second_and_third_but_not_fourth_character()
    {
        $user = User::factory()->create();
        Character::factory()->for($user)->create();
        $this->actingAs($user)->post('/characters', ['name' => 'Segundo','template_id'=>$this->template->id])->assertRedirect(route('dashboard'));
        app(CreateCharacterAction::class)->execute($user, 'Tercero', $this->template->id);
        $this->assertSame(3, $user->characters()->count());
        $this->expectException(ValidationException::class);
        app(CreateCharacterAction::class)->execute($user, 'Cuarto', $this->template->id);
    }

    public function test_user_can_only_view_own_character()
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownCharacter = Character::factory()->selectedFor($owner)->create();
        $otherCharacter = Character::factory()->selectedFor($otherUser)->create();
        $this->actingAs($owner)->get(route('characters.show', $ownCharacter))->assertOk();
        $this->actingAs($owner)->get(route('characters.show', $otherCharacter))->assertForbidden();
    }

    public function test_invalid_name_is_rejected()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/characters', ['name' => 'Mal@Nombre!','template_id'=>$this->template->id])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('characters', 0);
    }

    public function test_duplicate_name_is_rejected_case_insensitively()
    {
        $owner = User::factory()->create();
        Character::factory()->for($owner)->create(['name' => 'Dragon']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/characters', ['name' => 'dragon','template_id'=>$this->template->id])->assertSessionHasErrors('name');
        $this->assertSame(1, Character::query()->count());
    }

    public function test_dashboard_redirects_without_character_and_loads_with_character()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/')->assertRedirect(route('characters.create'));
        Character::factory()->selectedFor($user)->create();
        $this->actingAs($user)->get('/')->assertOk()->assertViewIs('game-home.index');
    }

    public function test_character_policy_is_registered_and_enforces_rules()
    {
        $user = User::factory()->create();
        $character = Character::factory()->for($user)->create();
        Character::factory()->for($user)->count(2)->create();
        $otherUser = User::factory()->create();
        $this->assertInstanceOf(CharacterPolicy::class, policy($character));
        $this->assertFalse($user->can('create', Character::class));
        $this->assertTrue($user->can('view', $character));
        $this->assertFalse($otherUser->can('view', $character));
    }
}
