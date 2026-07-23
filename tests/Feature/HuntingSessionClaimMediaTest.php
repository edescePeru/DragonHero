<?php

namespace Tests\Feature;

use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Domain\Hunts\Rewards\HuntRewardService;
use App\Domain\Hunts\Sessions\HuntingSessionPresentationService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\HuntReward;
use App\Models\HuntRewardItem;
use App\Models\HuntingSession;
use App\Models\Item;
use App\Models\ItemRarity;
use App\Models\MonsterLootEntry;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonImmutable;
use Database\Seeders\CharacterLevelRequirementSeeder;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'drop_probability_ppm' => 1000000,
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

    public function test_pending_unique_rewards_keep_rarity_identity_and_use_batched_visual_presentation()
    {
        list($character, $reward, $line) = $this->pendingReward();
        $common = ItemRarity::where('code', 'common')->firstOrFail();
        $legendary = ItemRarity::where('code', 'legendary')->firstOrFail();
        $legendary->update(['border_color_hex' => '#123456', 'inner_glow_color_hex' => '#654321']);
        $unique = $this->uniqueItem('pending-rarity-sword', 'Espada de rareza pendiente', [$common->id, $legendary->id]);

        $line->update($this->rewardItemAttributes($unique, $common, 1));
        HuntRewardItem::create(array_merge(
            $line->only(['hunt_reward_id', 'hunt_enemy_id', 'source_instance_identifier']),
            $this->rewardItemAttributes($unique, $legendary, 2)
        ));

        $stackable = Item::create([
            'code' => 'pending-stackable',
            'name' => 'Material pendiente',
            'item_type' => 'material',
            'equipment_type' => null,
            'rarity' => 'common',
            'is_stackable' => true,
            'max_stack' => 99,
            'status' => 'active',
        ]);
        foreach ([2, 3] as $quantity) {
            HuntRewardItem::create(array_merge(
                $line->only(['hunt_reward_id', 'hunt_enemy_id', 'source_instance_identifier']),
                $this->rewardItemAttributes($stackable, null, $quantity)
            ));
        }

        $summary = app(HuntRewardService::class)->summaryPendingForCharacter($character);
        $uniqueRows = collect($summary['items'])->where('item_id', $unique->id)->values();
        $stackableRow = collect($summary['items'])->firstWhere('item_id', $stackable->id);

        $this->assertCount(2, $uniqueRows);
        $this->assertSame([$common->id, $legendary->id], $uniqueRows->pluck('item_rarity_id')->sort()->values()->all());
        $this->assertSame([1, 2], $uniqueRows->pluck('quantity')->sort()->values()->all());
        $this->assertSame(5, $stackableRow['quantity']);
        $this->assertFalse($stackableRow['is_unique']);
        $this->assertNull($stackableRow['item_rarity_id']);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });
        $decorated = app(HuntingSessionPresentationService::class)->decoratePendingSummary($summary);
        $legendaryRow = collect($decorated['items'])->first(function ($item) use ($legendary) {
            return $item['item_rarity_id'] === $legendary->id;
        });
        $decoratedStackable = collect($decorated['items'])->firstWhere('item_id', $stackable->id);

        $this->assertSame('Legendario', $legendaryRow['rarity_name']);
        $this->assertSame('18, 52, 86', $legendaryRow['css_variables']['--rarity-border-rgb']);
        $this->assertSame('101, 67, 33', $legendaryRow['css_variables']['--rarity-glow-rgb']);
        $this->assertCount(7, $legendaryRow['css_variables']);
        $this->assertNull($decoratedStackable['rarity_visual']);
        $this->assertNull($decoratedStackable['css_variables']);
        $this->assertSame(1, collect($queries)->filter(function ($sql) {
            return strpos($sql, 'from `item_rarities`') !== false;
        })->count());
        $this->assertSame(1, collect($queries)->filter(function ($sql) {
            return strpos($sql, 'from `items`') !== false;
        })->count());

        $html = view('components.hunting.pending-loot', ['summary' => $decorated, 'character' => $character])->render();
        $this->assertStringContainsString('item-rarity-visual', $html);
        $this->assertStringContainsString('Legendario', $html);
        $this->assertStringContainsString('--rarity-border-rgb:18, 52, 86', $html);
        $this->assertStringNotContainsString('Instancia #', $html);
    }

    public function test_claimed_unique_reward_keeps_rarity_contract_for_dynamic_inventory()
    {
        list($character, $reward, $line) = $this->pendingReward();
        $legendary = ItemRarity::where('code', 'legendary')->firstOrFail();
        $unique = $this->uniqueItem('claimed-rarity-sword', 'Espada legendaria reclamada', [$legendary->id]);
        $line->update($this->rewardItemAttributes($unique, $legendary, 1));

        $data = $this->actingAs($character->user)
            ->postJson(route('characters.hunt-rewards.claim', $character))
            ->assertOk()
            ->json();
        $instance = collect($data['item_instances'])->firstWhere('item_id', $unique->id);

        $this->assertNotNull($instance);
        $this->assertSame('Legendario', $instance['rarity_name']);
        $this->assertCount(7, $instance['css_variables']);
        $this->assertNotEmpty($instance['public_reference']);
        $this->assertSame(0, $instance['refinement_level']);

        $script = file_get_contents(resource_path('views/characters/hunting-sessions/show.blade.php'));
        $this->assertStringContainsString("const rarityCssVariables=['--rarity-border-rgb','--rarity-border-opacity','--rarity-border-width','--rarity-glow-rgb','--rarity-glow-opacity','--rarity-glow-blur','--rarity-glow-spread']", $script);
        $this->assertStringContainsString("card.style.setProperty(variable,String(item.css_variables[variable]))", $script);
        $this->assertStringContainsString("if(item.public_reference)", $script);
        $this->assertStringNotContainsString('style.cssText', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    private function pendingReward()
    {
        $this->seed(WorldCatalogSeeder::class);
        $this->seed(CharacterLevelRequirementSeeder::class);
        MonsterLootEntry::query()->update([
            'drop_probability_ppm' => 1000000,
            'minimum_quantity' => 1,
            'maximum_quantity' => 1,
        ]);
        $character = Character::factory()->selected()->for(User::factory())->create(['base_attack' => 500]);
        $session = HuntingSession::findOrFail(app(HuntingSessionService::class)->start(
            $character,
            Zone::where('code', 'grey_oak_forest')->firstOrFail()
        )->id());
        app(HuntingSessionService::class)->tick($character, $session);
        $reward = HuntReward::latest('id')->firstOrFail();

        return [$character, $reward, $reward->items()->firstOrFail()];
    }

    private function uniqueItem($code, $name, array $rarityIds)
    {
        $item = Item::create([
            'code' => $code,
            'name' => $name,
            'item_type' => 'equipment',
            'equipment_type' => 'weapon',
            'rarity' => 'common',
            'is_stackable' => false,
            'max_stack' => 1,
            'required_level' => 1,
            'status' => 'active',
        ]);
        $item->allowedRarities()->sync($rarityIds);

        return $item;
    }

    private function rewardItemAttributes(Item $item, $rarity, $quantity)
    {
        return [
            'item_id' => $item->id,
            'item_code_snapshot' => $item->code,
            'item_name_snapshot' => $item->name,
            'quantity' => $quantity,
            'item_rarity_id' => $rarity ? $rarity->id : null,
            'rarity_code_snapshot' => $rarity ? $rarity->code : null,
            'rarity_name_snapshot' => $rarity ? $rarity->name : null,
            'rarity_roll_metadata' => null,
        ];
    }
}
