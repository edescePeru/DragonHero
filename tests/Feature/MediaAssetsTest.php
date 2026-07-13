<?php

namespace Tests\Feature;

use App\Domain\Media\MediaAssetService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\Item;
use App\Models\MediaAsset;
use App\Models\Monster;
use App\Models\Region;
use App\Models\World;
use App\Models\Zone;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class MediaAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function service()
    {
        return $this->app->make(MediaAssetService::class);
    }

    private function seededModels()
    {
        $this->seed(WorldCatalogSeeder::class);
        return [World::firstOrFail(), Region::firstOrFail(), Zone::firstOrFail(), Monster::firstOrFail(), Item::firstOrFail()];
    }

    public function test_supported_models_expose_media_queries_and_aliases()
    {
        $models = array_merge([Character::factory()->create()], $this->seededModels());
        foreach ($models as $model) {
            $asset = $this->service()->attach($model, ['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => 'test/example.webp']);
            $this->assertSame(Relation::getMorphedModel($asset->mediable_type), get_class($model));
            $this->assertStringNotContainsString('App\\Models', $asset->mediable_type);
            $this->assertSame($asset->id, $model->mediaAssetsOfType(MediaAssetType::ICON)->firstOrFail()->id);
        }
    }

    public function test_service_normalizes_paths_stores_metadata_and_builds_url_explicitly()
    {
        $monster = $this->seededModels()[3];
        $asset = $this->service()->attach($monster, ['asset_type' => MediaAssetType::SPRITE_ATTACK, 'disk' => 'public', 'path' => 'monsters\\grey-wolf\\attack.webp', 'metadata' => ['frame_width' => 64, 'frame_count' => 6, 'fps' => 12], 'is_primary' => true]);
        $this->assertSame('monsters/grey-wolf/attack.webp', $asset->path);
        $this->assertSame(6, $asset->metadata['frame_count']);
        $this->assertStringContainsString('monsters/grey-wolf/attack.webp', $asset->url());
        $this->assertArrayNotHasKey('url', $asset->toArray());
    }

    public function test_primary_asset_is_unique_per_model_and_type_through_service()
    {
        $monster = $this->seededModels()[3];
        $first = $this->service()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'monsters/a.webp', 'is_primary' => true]);
        $second = $this->service()->attach($monster, ['asset_type' => MediaAssetType::PORTRAIT, 'disk' => 'public', 'path' => 'monsters/b.webp']);
        $this->service()->setPrimary($second);
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame($second->id, $monster->primaryMediaAsset(MediaAssetType::PORTRAIT)->firstOrFail()->id);
    }

    /** @dataProvider invalidPathProvider */
    public function test_service_rejects_invalid_paths($path)
    {
        $monster = $this->seededModels()[3];
        $this->expectException(InvalidArgumentException::class);
        $this->service()->attach($monster, ['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => $path]);
    }

    public function invalidPathProvider()
    {
        return [['/images/wolf.webp'], ['https://cdn.test/wolf.webp'], ['../private/wolf.webp'], ['C:\\images\\wolf.webp'], [' images/wolf.webp'], ["images/wolf\0.webp"]];
    }

    public function test_media_is_lazy_unless_explicitly_eager_loaded()
    {
        $monster = $this->seededModels()[3];
        $this->service()->attach($monster, ['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => 'monsters/icon.webp']);
        DB::flushQueryLog(); DB::enableQueryLog();
        $plain = Monster::findOrFail($monster->id);
        $this->assertFalse($plain->relationLoaded('mediaAssets'));
        $this->assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
        $loaded = Monster::with(['mediaAssets' => function ($query) { $query->where('asset_type', MediaAssetType::ICON); }])->findOrFail($monster->id);
        $this->assertTrue($loaded->relationLoaded('mediaAssets'));
        $this->assertCount(1, $loaded->mediaAssets);
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_deleting_model_removes_references_but_not_physical_files()
    {
        Storage::fake('public');
        Storage::disk('public')->put('monsters/orphan-check.webp', 'content');
        $monster = Monster::create(['code' => 'media-test-monster', 'name' => 'Media test monster', 'level' => 1, 'max_health' => 10, 'attack' => 1, 'defense' => 1, 'accuracy_rate' => 80, 'evasion_rate' => 5, 'critical_chance' => 5, 'status' => 'active', 'experience_reward' => 0]);
        $asset = $this->service()->attach($monster, ['asset_type' => MediaAssetType::ICON, 'disk' => 'public', 'path' => 'monsters/orphan-check.webp']);
        $monster->delete();
        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertExists('monsters/orphan-check.webp');
    }

    public function test_factory_creates_only_a_reference()
    {
        Storage::fake('public');
        $monster = $this->seededModels()[3];
        $asset = MediaAsset::factory()->for($monster, 'mediable')->create();
        $this->assertSame('test/example.webp', $asset->path);
        Storage::disk('public')->assertMissing($asset->path);
    }
}
