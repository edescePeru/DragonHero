<?php

namespace App\Domain\Characters\Overview;

use App\Domain\Characters\CharacterStatsCalculator;
use App\Domain\Characters\Appearance\CharacterAppearanceService;
use App\Domain\Characters\Progression\CharacterProgressionService;
use App\Domain\Equipment\CharacterEquipmentSummaryService;
use App\Domain\Equipment\CharacterEquipmentSlot;
use App\Domain\Inventory\CharacterInventorySummaryService;
use App\Domain\Media\MediaAssetType;
use App\Domain\Media\CatalogImages\CatalogImageService;
use App\Domain\Media\CatalogImages\CatalogImageType;
use App\Domain\Media\CharacterVisuals\CharacterVisualAssetService;
use App\Domain\Wallet\WalletService;
use App\Models\Character;
use App\Models\Item;

final class CharacterOverviewService
{
    private $stats;
    private $progression;
    private $equipment;
    private $inventory;
    private $wallet;
    private $catalogImages;
    private $characterVisuals;
    private $appearance;

    public function __construct(
        CharacterStatsCalculator $stats,
        CharacterProgressionService $progression,
        CharacterEquipmentSummaryService $equipment,
        CharacterInventorySummaryService $inventory,
        WalletService $wallet,
        CatalogImageService $catalogImages,
        CharacterVisualAssetService $characterVisuals,
        CharacterAppearanceService $appearance
    ) {
        $this->stats = $stats;
        $this->progression = $progression;
        $this->equipment = $equipment;
        $this->inventory = $inventory;
        $this->wallet = $wallet;
        $this->catalogImages = $catalogImages;
        $this->characterVisuals = $characterVisuals;
        $this->appearance = $appearance;
    }

    public function snapshot(Character $character): array
    {
        $character->loadMissing([
            'characterClass',
            'characterTemplate.mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::BASE_VISUAL)
                    ->where('is_primary', true)->orderBy('id');
            },
            'mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::PORTRAIT)
                    ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
            },
        ]);

        $breakdown = $this->stats->breakdown($character);
        $effective = $breakdown->effective();
        $progress = $this->progression->experienceProgress($character);
        $equipment = $this->equipment->snapshot($character, $breakdown->equipmentSources())->toArray();
        $inventory = $this->inventory->snapshot($character);
        $items = $this->loadItems($equipment, $inventory);

        $equipmentRows = array_map(function ($entry) use ($items, $character) {
            $item = $entry['item_id'] ? $items->get($entry['item_id']) : null;
            return $this->equipmentRow($entry, $item, $character);
        }, $equipment);
        $equipmentRows[] = [
            'slot' => 'pet', 'slot_label' => 'Mascota', 'occupied' => false,
            'visual_only' => true, 'coming_soon' => true,
            'details' => ['Las mascotas estaran disponibles proximamente.'],
            'unequip_url' => null, 'detail_icon_url' => null,
        ];

        $inventoryRows = [];
        foreach ($inventory['stackable_items'] as $entry) {
            foreach ($entry['stack_quantities'] as $stackQuantity) {
                $inventoryRows[] = $this->stackableRow($entry, $items->get($entry['item_id']), $stackQuantity);
            }
        }
        foreach ($inventory['item_instances'] as $entry) {
            $inventoryRows[] = $this->instanceRow($entry, $items->get($entry['item_id']), $character);
        }

        $capacity = $inventory['inventory_status'];
        $portrait = $character->mediaAssets->first();
        $baseVisual = $this->characterVisuals->presentation($character->characterTemplate);

        return [
            'appearance' => $this->appearance->presentation($character),
            'summary' => [
                'name' => $character->name,
                'class_name' => $character->characterClass ? $character->characterClass->name : 'Sin clase',
                'level' => $progress->level(),
                'experience' => $progress->experience(),
                'gold' => $this->wallet->balance($character)->balance(),
                'portrait_url' => $portrait ? $portrait->url() : null,
                'portrait_initial' => mb_strtoupper(mb_substr($character->name, 0, 1)),
                'base_visual_url' => $baseVisual->url256(),
                'base_visual_is_fallback' => !$baseVisual->exists(),
                'progress' => [
                    'percentage' => $progress->percentage(),
                    'maximum_level' => $progress->isMaximumLevel(),
                    'next_level' => $progress->nextLevel(),
                    'current' => $progress->experienceInLevel(),
                    'required' => $progress->experienceRequiredInLevel(),
                    'remaining' => $progress->experienceRemaining(),
                ],
            ],
            'equipment' => $equipmentRows,
            'inventory' => [
                'entries' => $inventoryRows,
                'capacity' => $capacity,
                'empty_slots' => max(0, (int) $capacity['current_free_slots']),
            ],
            'stats' => $this->statsRows($effective),
        ];
    }

    private function loadItems(array $equipment, array $inventory)
    {
        $ids = collect($equipment)->pluck('item_id')
            ->merge(collect($inventory['stackable_items'])->pluck('item_id'))
            ->merge(collect($inventory['item_instances'])->pluck('item_id'))
            ->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Item::whereIn('id', $ids)->with([
            'allowedCharacterClasses',
            'mediaAssets' => function ($query) {
                $query->where('asset_type', MediaAssetType::ICON)
                    ->orderByDesc('is_primary')->orderBy('sort_order')->orderBy('id');
            },
        ])->get()->keyBy('id');
    }

    private function equipmentRow(array $entry, $item, Character $character): array
    {
        if (!$entry['occupied']) {
            return array_merge($entry, ['details' => ['Slot disponible'], 'unequip_url' => null, 'detail_icon_url' => null]);
        }

        return array_merge($entry, [
            'icon_url' => $this->iconUrl($item, 64),
            'detail_icon_url' => $this->iconUrl($item, 128),
            'details' => $this->itemDetails($item, $entry['refinement_level'], $entry['total_bonuses']),
            'unequip_url' => route('characters.equipment.unequip', $character),
        ]);
    }

    private function stackableRow(array $entry, $item, $stackQuantity): array
    {
        return [
            'kind' => 'stackable',
            'item_id' => $entry['item_id'],
            'item_code' => $entry['item_code'],
            'name' => $entry['item_name'],
            'icon_url' => $this->iconUrl($item, 64),
            'detail_icon_url' => $this->iconUrl($item, 128),
            'quantity' => (int) $stackQuantity,
            'total_quantity' => $entry['quantity'],
            'max_stack' => $entry['max_stack'],
            'locked_quantity' => $entry['locked_quantity'],
            'details' => array_merge($this->itemDetails($item, null, []), [
                'Cantidad disponible: '.$entry['available_quantity'],
                'Cantidad bloqueada: '.$entry['locked_quantity'],
            ]),
            'equip_options' => [],
        ];
    }

    private function instanceRow(array $entry, $item, Character $character): array
    {
        $options = [];
        foreach ($entry['eligibility_by_slot'] as $slot => $eligibility) {
            $options[] = [
                'slot' => $slot,
                'slot_label' => $this->slotLabel($slot),
                'eligible' => (bool) $eligibility['eligible'],
                'message' => $eligibility['message'],
                'url' => route('characters.equipment.equip', $character),
                'loadout' => isset($eligibility['loadout']) ? $eligibility['loadout'] : null,
            ];
        }

        return [
            'kind' => 'instance',
            'name' => $entry['item_name'],
            'icon_url' => $this->iconUrl($item, 64),
            'detail_icon_url' => $this->iconUrl($item, 128),
            'quantity' => 1,
            'locked_quantity' => 0,
            'instance_uuid' => $entry['uuid'],
            'details' => $this->itemDetails($item, $entry['refinement_level'], $entry['bonuses']),
            'equip_options' => $options,
        ];
    }

    private function itemDetails($item, $refinementLevel, array $bonuses): array
    {
        if (!$item) {
            return ['Información del objeto no disponible'];
        }

        $details = [
            'Tipo: '.($item->item_type ?: 'Sin tipo'),
            'Rareza: '.($item->rarity ?: 'Sin rareza'),
        ];
        if ($item->description) {
            $details[] = 'Descripción: '.$item->description;
        }
        if ($item->equipment_type) {
            $details[] = 'Equipamiento: '.$item->equipment_type;
            $details[] = 'Nivel requerido: '.(int) $item->required_level;
            $classes = $item->allowedCharacterClasses->pluck('name')->implode(', ');
            $details[] = 'Clases: '.($classes ?: 'Todas');
        }
        if ($refinementLevel !== null) {
            $details[] = 'Refinamiento: +'.(int) $refinementLevel;
        }
        foreach ($this->bonusLabels() as $key => $label) {
            if (isset($bonuses[$key]) && (float) $bonuses[$key] != 0.0) {
                $details[] = $label.': +'.$bonuses[$key];
            }
        }
        return $details;
    }

    private function iconUrl($item, $size)
    {
        if (!$item) return $this->catalogImages->presentation(CatalogImageType::ITEM)->url($size);
        return $this->catalogImages->presentationFor($item, CatalogImageType::ITEM)->url($size);
    }

    private function statsRows($stats): array
    {
        return [
            ['label' => 'Vida', 'value' => $stats->currentHealth().' / '.$stats->maxHealth()],
            ['label' => 'Ataque', 'value' => $stats->attack()],
            ['label' => 'Defensa', 'value' => $stats->defense()],
            ['label' => 'Precisión', 'value' => $stats->accuracyRate().'%'],
            ['label' => 'Evasión', 'value' => $stats->evasionRate().'%'],
            ['label' => 'Crítico', 'value' => $stats->criticalChance().'%'],
            ['label' => 'Multiplicador crítico', 'value' => 'x'.$stats->criticalDamageMultiplier()],
            ['label' => 'Velocidad de ataque', 'value' => $stats->attackSpeed()],
            ['label' => 'Reducción de daño', 'value' => $stats->damageReductionRate().'%'],
            ['label' => 'Poder', 'value' => $stats->power()],
            ['label' => 'Bonus de loot', 'value' => $stats->lootBonus().'%'],
            ['label' => 'Bonus de experiencia', 'value' => $stats->experienceBonus().'%'],
            ['label' => 'Bonus de oro', 'value' => $stats->goldBonus().'%'],
        ];
    }

    private function bonusLabels(): array
    {
        return ['max_health' => 'Vida', 'attack' => 'Ataque', 'defense' => 'Defensa', 'accuracy' => 'Precisión', 'evasion' => 'Evasión', 'critical_chance' => 'Crítico', 'attack_speed' => 'Velocidad de ataque'];
    }

    private function slotLabel(string $slot): string
    {
        return CharacterEquipmentSlot::label($slot);
    }
}
