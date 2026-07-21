<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Characters\Appearance\CharacterAppearanceService;
use App\Domain\Equipment\CharacterEquipmentSlot;
use App\Domain\Inventory\CharacterInventorySummaryService;
use App\Domain\Media\MediaAssetType;
use App\Models\Character;
use App\Models\CombatSession;
use App\Models\Item;
use App\Models\Monster;

final class ManualCombatPresentationService
{
    private $appearance;
    private $inventory;

    public function __construct(CharacterAppearanceService $appearance, CharacterInventorySummaryService $inventory)
    {
        $this->appearance = $appearance;
        $this->inventory = $inventory;
    }

    public function prepare(Character $character, CombatSession $combat)
    {
        $combat->loadMissing('participants');
        $monsterIds = $combat->participants
            ->where('participant_type', CombatParticipantType::MONSTER)
            ->pluck('source_id')
            ->unique()
            ->values();

        $monsters = $monsterIds->isEmpty() ? collect() : Monster::whereIn('id', $monsterIds)
            ->with(['mediaAssets' => function ($query) {
                $query->where('is_primary', true)
                    ->whereIn('asset_type', [MediaAssetType::PORTRAIT, MediaAssetType::SPRITE_IDLE, MediaAssetType::IMAGE]);
            }])
            ->get()
            ->keyBy('id');

        $visuals = [];
        $characterAppearance = $this->appearance->presentation($character);
        foreach ($combat->participants as $participant) {
            if ($participant->participant_type === CombatParticipantType::CHARACTER) {
                $visuals[(int) $participant->id] = [
                    'kind' => 'character',
                    'appearance' => $characterAppearance,
                    'image_url' => null,
                ];
                continue;
            }

            $monster = $monsters->get((int) $participant->source_id);
            $asset = $monster && $monster->relationLoaded('mediaAssets') ? $monster->mediaAssets->sortBy(function ($media) {
                $priority = [MediaAssetType::PORTRAIT => 1, MediaAssetType::SPRITE_IDLE => 2, MediaAssetType::IMAGE => 3];
                return isset($priority[$media->asset_type]) ? $priority[$media->asset_type] : 99;
            })->first() : null;
            $visuals[(int) $participant->id] = [
                'kind' => 'monster',
                'appearance' => null,
                'image_url' => $asset ? $asset->url() : null,
            ];
        }

        $characterParticipant = $combat->participants->first(function ($participant) {
            return $participant->participant_type === CombatParticipantType::CHARACTER;
        });

        return [
            'participant_visuals' => $visuals,
            'background' => $this->background($combat),
            'equipment' => $this->equipment($character),
            'inventory' => $this->decorateInventory($this->inventory->snapshot($character)),
            'combat_stats' => $this->combatStats($characterParticipant ? $characterParticipant->stats_snapshot : []),
        ];
    }

    public function decorateRewards(array $rewards)
    {
        $ids = collect(isset($rewards['items']) ? $rewards['items'] : [])->pluck('item_id')->unique()->values();
        $items = $this->itemsWithIcons($ids);
        $rewards['items'] = array_map(function ($row) use ($items) {
            $row['image_url'] = $this->iconUrl($items->get((int) $row['item_id']));
            return $row;
        }, isset($rewards['items']) ? $rewards['items'] : []);
        return $rewards;
    }

    public function returnUrl(CombatSession $combat)
    {
        $combat->loadMissing('zone.region.world');
        if ($combat->zone && $combat->zone->region && $combat->zone->region->world) {
            return route('worlds.regions.show', [$combat->zone->region->world, $combat->zone->region]);
        }
        return route('worlds.index');
    }

    private function background(CombatSession $combat)
    {
        $combat->loadMissing('zone.mediaAssets');
        $asset = $combat->zone ? $combat->zone->mediaAssets->first(function ($media) {
            return $media->asset_type === MediaAssetType::BACKGROUND && (bool) $media->is_primary;
        }) : null;
        return ['url' => $asset ? $asset->url() : null, 'transparent' => $asset === null];
    }

    private function equipment(Character $character)
    {
        $character->loadMissing(['equipment.itemInstance.item.mediaAssets']);
        $bySlot = $character->equipment->keyBy('slot');
        $rows = [];
        foreach (CharacterEquipmentSlot::all() as $slot) {
            $equipped = $bySlot->get($slot);
            $instance = $equipped ? $equipped->itemInstance : null;
            $item = $instance ? $instance->item : null;
            $rows[] = ['slot' => $slot, 'label' => CharacterEquipmentSlot::label($slot), 'occupied' => (bool) $item, 'item_name' => $item ? $item->name : null, 'refinement_level' => $instance ? (int) $instance->refinement_level : null, 'image_url' => $this->iconUrl($item)];
        }
        return $rows;
    }

    private function decorateInventory(array $inventory)
    {
        $ids = collect($inventory['stackable_items'])->pluck('item_id')->merge(collect($inventory['item_instances'])->pluck('item_id'))->unique()->values();
        $items = $this->itemsWithIcons($ids);
        foreach (['stackable_items', 'item_instances'] as $key) {
            $inventory[$key] = array_map(function ($row) use ($items) {
                $row['image_url'] = $this->iconUrl($items->get((int) $row['item_id']));
                return $row;
            }, $inventory[$key]);
        }
        return $inventory;
    }

    private function combatStats(array $snapshot)
    {
        $keys = ['max_health', 'attack', 'defense', 'accuracy_rate', 'evasion_rate', 'critical_chance', 'critical_damage_multiplier', 'attack_speed', 'damage_reduction_rate'];
        return array_intersect_key($snapshot, array_flip($keys));
    }

    private function itemsWithIcons($ids)
    {
        return $ids->isEmpty() ? collect() : Item::whereIn('id', $ids)->with(['mediaAssets' => function ($query) {
            $query->where('asset_type', MediaAssetType::ICON)->where('is_primary', true);
        }])->get()->keyBy('id');
    }

    private function iconUrl($item)
    {
        if (!$item) return null;
        $asset = $item->relationLoaded('mediaAssets') ? $item->mediaAssets->first() : null;
        return $asset ? $asset->url() : null;
    }
}
