<?php

namespace Tests\Feature\Admin\Content;

use App\Domain\Admin\Content\ContentAdminService;
use App\Domain\Hunts\Sessions\HuntingSessionPresentationService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\Region;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ZoneCombatBackgroundAdminTest extends TestCase
{
    use RefreshDatabase;

    private $admin;
    private $region;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['game_admin.emails' => ['zone-images@example.test']]);
        $this->admin = User::factory()->create(['email' => 'zone-images@example.test']);
        $this->seed(WorldCatalogSeeder::class);
        $this->region = Region::firstOrFail();
    }

    public function test_forms_expose_large_combat_preview_and_zone_can_be_created_without_image()
    {
        $this->actingAs($this->admin)->get(route('admin.content.zones.create'))->assertOk()
            ->assertSee('multipart/form-data', false)->assertSee('name="combat_background"', false)
            ->assertSee('name="remove_combat_background"', false)->assertSee('height:24rem', false)
            ->assertSee('background-size:cover', false)->assertSee('background-position:center', false)
            ->assertSee('Cacería automática')->assertSee('Combate manual')->assertSee('1920 × 1080');

        $this->post(route('admin.content.zones.store'), $this->payload('zone_without_background'))->assertRedirect();
        $zone = Zone::where('code', 'zone_without_background')->firstOrFail();
        $this->assertFalse($zone->mediaAssetsOfType(MediaAssetType::BACKGROUND)->exists());
    }

    public function test_create_replace_keep_and_remove_background_through_same_form()
    {
        $this->actingAs($this->admin)->post(route('admin.content.zones.store'), $this->payload('zone_with_background', $this->image('first.png', 1600, 900)))->assertRedirect();
        $zone = Zone::where('code', 'zone_with_background')->firstOrFail();
        $first = $zone->primaryMediaAsset(MediaAssetType::BACKGROUND)->firstOrFail();
        Storage::disk('public')->assertExists($first->path);
        $this->assertSame('zone', $first->mediable_type);
        $this->assertSame(1600, $first->width);

        $this->get(route('admin.content.zones.edit', $zone))->assertOk()->assertSee('first.png')->assertSee('1600 × 900 px')->assertSee('PNG')->assertSee($first->url(), false);
        $this->put(route('admin.content.zones.update', $zone), $this->payload($zone->code))->assertRedirect();
        $this->assertSame($first->id, $zone->primaryMediaAsset(MediaAssetType::BACKGROUND)->firstOrFail()->id);

        $this->put(route('admin.content.zones.update', $zone), $this->payload($zone->code, $this->image('second.jpg', 1500, 850)))->assertRedirect();
        $second = $zone->primaryMediaAsset(MediaAssetType::BACKGROUND)->firstOrFail();
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, $zone->mediaAssetsOfType(MediaAssetType::BACKGROUND)->where('is_primary', true)->count());
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);

        $this->put(route('admin.content.zones.update', $zone), array_merge($this->payload($zone->code), ['remove_combat_background' => 1]))->assertRedirect();
        $this->assertFalse($zone->mediaAssetsOfType(MediaAssetType::BACKGROUND)->exists());
        Storage::disk('public')->assertMissing($second->path);
    }

    public function test_invalid_and_conflicting_uploads_are_rejected_without_partial_zone_or_file()
    {
        $svg = UploadedFile::fake()->createWithContent('scene.svg', '<svg/>');
        $this->actingAs($this->admin)->post(route('admin.content.zones.store'), $this->payload('invalid_zone', $svg))->assertSessionHasErrors('combat_background');
        $this->assertDatabaseMissing('zones', ['code' => 'invalid_zone']);

        $zone = Zone::firstOrFail();
        $this->put(route('admin.content.zones.update', $zone), array_merge($this->payload($zone->code, $this->image('conflict.png', 800, 500)), ['remove_combat_background' => 1]))->assertSessionHasErrors('remove_combat_background');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_saved_background_is_consumed_by_automatic_hunting_presentation_and_normal_user_is_forbidden()
    {
        $this->actingAs($this->admin)->post(route('admin.content.zones.store'), $this->payload('presented_zone', $this->image('hunting.webp', 1366, 768)))->assertRedirect();
        $zone = Zone::where('code', 'presented_zone')->firstOrFail();
        $character = Character::factory()->create();
        $presentation = app(HuntingSessionPresentationService::class)->prepare($character, $zone);
        $this->assertFalse($presentation['background']['transparent']);
        $this->assertSame($zone->primaryMediaAsset(MediaAssetType::BACKGROUND)->firstOrFail()->url(), $presentation['background']['url']);
        $this->actingAs(User::factory()->create())->get(route('admin.content.zones.edit', $zone))->assertForbidden();
    }

    public function test_failed_domain_write_cleans_staged_file_and_rolls_back_zone()
    {
        $data = $this->payload('rollback_background', $this->image('rollback.png', 1200, 700));
        $data['encounter_sizes'] = [1 => 90, 2 => 5, 3 => 4];
        try { app(ContentAdminService::class)->saveZone($data); $this->fail('Invalid encounter total accepted.'); }
        catch (\InvalidArgumentException $exception) { $this->assertTrue(true); }
        $this->assertDatabaseMissing('zones', ['code' => 'rollback_background']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function payload($code, UploadedFile $image = null)
    {
        return array_merge(['region_id'=>$this->region->id,'code'=>$code,'name'=>'Zone background','description'=>null,'zone_type'=>'field','recommended_level_min'=>1,'recommended_level_max'=>5,'is_safe'=>0,'allows_hunting'=>1,'status'=>'active','sort_order'=>10,'encounter_sizes'=>[1=>100,2=>0,3=>0],'remove_combat_background'=>0],$image?['combat_background'=>$image]:[]);
    }

    private function image($name, $width, $height)
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return UploadedFile::fake()->image($name, $width, $height)->mimeType($extension === 'jpg' ? 'image/jpeg' : 'image/'.$extension);
    }
}
