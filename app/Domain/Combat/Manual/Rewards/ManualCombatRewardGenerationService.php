<?php

namespace App\Domain\Combat\Manual\Rewards;

use App\Domain\Combat\Manual\CombatParticipantType;
use App\Domain\Combat\Manual\ManualCombatEventService;
use App\Domain\Combat\Manual\ManualCombatEventType;
use App\Domain\Hunts\Rewards\MonsterRewardValueGenerator;
use App\Domain\Loot\LootGenerator;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\CombatParticipant;
use App\Models\CombatPendingReward;
use App\Models\CombatPendingRewardItem;
use App\Models\CombatSession;
use App\Models\Monster;
use App\Models\MonsterLootEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ManualCombatRewardGenerationService
{
    private $values; private $loot; private $events;
    public function __construct(MonsterRewardValueGenerator $values, LootGenerator $loot, ManualCombatEventService $events)
    { $this->values = $values; $this->loot = $loot; $this->events = $events; }

    public function generateLocked(CombatSession $combat, CombatParticipant $participant, $roundNumber)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if ($participant->participant_type !== CombatParticipantType::MONSTER || (int) $participant->combat_session_id !== (int) $combat->id) throw new RuntimeException('Reward source must be a Monster participant from this combat.');
        $existing = CombatPendingReward::where('combat_session_id', $combat->id)->where('source_participant_id', $participant->id)->lockForUpdate()->first();
        if ($existing) return $existing;

        $monster = Monster::whereKey($participant->source_id)->firstOrFail();
        $entries = MonsterLootEntry::where('monster_id', $monster->id)->where('status', CatalogStatus::ACTIVE)
            ->whereHas('item', function ($query) { $query->where('status', CatalogStatus::ACTIVE); })
            ->with('item.allowedRarities')->orderBy('sort_order')->orderBy('id')->get();
        $values = $this->values->generate($monster);
        $loot = $this->loot->generateFromLoadedEntries($monster, $entries);
        $now = CarbonImmutable::now();
        $reward = CombatPendingReward::create([
            'combat_session_id' => $combat->id,
            'source_participant_id' => $participant->id,
            'source_monster_id' => $monster->id,
            'source_identifier' => $participant->source_identifier,
            'experience_amount' => $values->experience(),
            'gold_amount' => $values->gold(),
            'status' => CombatPendingRewardStatus::PENDING,
            'generation_context' => ['version' => 1, 'monster_code' => $monster->code, 'monster_name' => $monster->name],
            'generated_at' => $now,
        ]);
        $publicItems = [];
        foreach ($loot->drops() as $drop) {
            $entry = $entries->firstWhere('item_id', $drop->itemId());
            $line = CombatPendingRewardItem::create([
                'combat_pending_reward_id' => $reward->id,
                'item_id' => $drop->itemId(),
                'item_code_snapshot' => $drop->itemCode(),
                'item_name_snapshot' => $drop->itemName(),
                'quantity' => $drop->quantity(),
                'loot_entry_id' => $entry ? $entry->id : null,
                'generation_metadata' => ['version' => 2, 'configured_probability_ppm' => $drop->configuredProbabilityPpm(), 'roll_ppm' => $drop->probabilityRollPpm()],
                'item_rarity_id' => $drop->resolvedItemRarityId(),
                'rarity_code_snapshot' => $drop->resolvedItemRarityCode(),
                'rarity_name_snapshot' => $drop->resolvedItemRarityId() ? \App\Models\ItemRarity::whereKey($drop->resolvedItemRarityId())->value('name') : null,
                'rarity_roll_metadata' => $drop->itemRarityRollResult() ? $drop->itemRarityRollResult()->metadata() : null,
            ]);
            $publicItems[] = ['item_id' => (int) $line->item_id, 'name' => $line->item_name_snapshot, 'quantity' => (int) $line->quantity];
        }
        $this->events->append($combat, ManualCombatEventType::REWARD_GENERATED, $roundNumber, $participant, [
            'version' => 1,
            'source_participant_id' => (int) $participant->id,
            'source' => ['type' => 'monster', 'name' => (string) $participant->display_name],
            'reward' => ['experience' => (int) $reward->experience_amount, 'gold' => (int) $reward->gold_amount, 'items' => $publicItems, 'status' => CombatPendingRewardStatus::PENDING],
        ]);
        return $reward;
    }
}
