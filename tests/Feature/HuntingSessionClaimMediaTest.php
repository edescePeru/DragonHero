<?php

namespace Tests\Feature;

use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\HuntReward;
use App\Models\HuntingSession;
use App\Models\MonsterLootEntry;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HuntingSessionClaimMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_response_keeps_item_icons_in_refreshed_inventory()
    {
        $this->seed(WorldCatalogSeeder::class);
        $this->seed(CharacterLevelRequirementSeeder::class);
        CarbonImmutable::setTestNow('2026-07-20 12:00:00');
        MonsterLootEntry::query()->update([
            'drop_chance_basis_points' => 10000,
            'minimum_quantity' => 1,
            'maximum_quantity' => 1,
        ]);

        $character = Character::factory()->selected()->for(User::factory())->create(['base_attack' => 500]);
        $zone = Zone::where('code', 'grey_oak_forest')->firstOrFail();
        $sessions = app(HuntingSessionService::class);
        $session = HuntingSession::findOrFail($sessions->start($character, $zone)->id());
        $sessions->tick($character, $session);
        $item = HuntReward::latest('id')->firstOrFail()->items()->firstOrFail()->item;
        $item->mediaAssets()->create([
            'asset_type' => MediaAssetType::ICON,
            'disk' => 'public',
            'path' => 'test/claimed-item-icon.webp',
            'mime_type' => 'image/webp',
            'width' => 128,
            'height' => 128,
            'file_size' => 1024,
            'metadata' => [],
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $data = $this->actingAs($character->user)
            ->postJson(route('characters.hunt-rewards.claim', $character))
            ->assertOk()
            ->json();
        $inventoryItem = collect($data['stackable_items'])->firstWhere('item_id', $item->id);

        $this->assertNotNull($inventoryItem);
        $this->assertSame(asset('storage/test/claimed-item-icon.webp'), $inventoryItem['image_url']);
        $this->assertSame(
            (int) CharacterItem::where('character_id', $character->id)->where('item_id', $item->id)->value('quantity'),
            $inventoryItem['quantity']
        );

        CarbonImmutable::setTestNow();
    }
}
