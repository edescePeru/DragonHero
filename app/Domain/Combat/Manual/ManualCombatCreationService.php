<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\CombatSide;
use App\Domain\Combat\Manual\Exceptions\ActiveManualCombatConflictException;
use App\Domain\Hunts\EncounterSizeSelector;
use App\Domain\Hunts\Sessions\HuntingSessionMode;
use App\Domain\Hunts\Sessions\HuntingSessionStatus;
use App\Domain\Hunts\WeightedMonsterSelector;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ManualCombatCreationService
{
    private $sizes;
    private $monsters;
    private $snapshots;
    private $states;
    private $read;
    private $events;
    private $turns;
    private $expiration;
    private $recovery;

    public function __construct(EncounterSizeSelector $sizes, WeightedMonsterSelector $monsters, CombatParticipantSnapshotFactory $snapshots, CombatStateRebuilder $states, ManualCombatReadService $read, ManualCombatEventService $events, ManualCombatTurnService $turns, ManualCombatExpirationService $expiration, ManualCombatRecoveryService $recovery)
    {
        $this->sizes = $sizes;
        $this->monsters = $monsters;
        $this->snapshots = $snapshots;
        $this->states = $states;
        $this->read = $read;
        $this->events = $events;
        $this->turns = $turns;
        $this->expiration = $expiration;
        $this->recovery = $recovery;
    }

    public function create(User $user, Character $character, HuntingSession $huntingSession)
    {
        return DB::transaction(function () use ($user, $character, $huntingSession) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedCharacter->user_id !== (int) $user->id) throw new AuthorizationException('This Character does not belong to the authenticated user.');

            $lockedHunting = HuntingSession::whereKey($huntingSession->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedHunting->character_id !== (int) $lockedCharacter->id) throw new AuthorizationException('This HuntingSession does not belong to the Character.');
            if ($lockedHunting->mode !== HuntingSessionMode::CONNECTED || $lockedHunting->status !== HuntingSessionStatus::RUNNING) throw new InvalidArgumentException('The HuntingSession is not available for manual combat.');

            $existing = CombatSession::where('active_slot', $lockedCharacter->id)->lockForUpdate()->first();
            if ($existing) {
                $this->expiration->expireIfNeededLocked($existing);
                if ($existing->status === ManualCombatStatus::ACTIVE) $this->recovery->recoverLocked($existing);
                if (in_array($existing->status,[ManualCombatStatus::ACTIVE,ManualCombatStatus::WAITING_PLAYER],true)) {
                    if ((int) $existing->hunting_session_id === (int) $lockedHunting->id) return $this->read->read($user, $existing);
                    throw new ActiveManualCombatConflictException('The Character already has an active manual combat.');
                }
            }

            $zone = Zone::whereKey($lockedHunting->zone_id)->lockForUpdate()->firstOrFail();
            if ($zone->status !== CatalogStatus::ACTIVE || !$zone->allows_hunting) throw new InvalidArgumentException('The HuntingSession Zone is unavailable.');
            $configs = ZoneEncounterSize::where('zone_id', $zone->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $count = $this->sizes->select($configs);
            $selected = $this->monsters->selectMany($this->monsters->eligibleEntries($zone, $lockedCharacter), $count);
            $now = CarbonImmutable::now();

            $combat = CombatSession::create([
                'owner_user_id' => $user->id,
                'character_id' => $lockedCharacter->id,
                'hunting_session_id' => $lockedHunting->id,
                'zone_id' => $zone->id,
                'mode' => ManualCombatMode::MANUAL,
                'status' => ManualCombatStatus::PENDING,
                'round_number' => 1,
                'current_participant_id' => null,
                'lock_version' => 0,
                'active_slot' => $lockedCharacter->id,
                'started_at' => $now,
                'last_action_at' => $now,
            ]);

            $characterSnapshot = $this->snapshots->forCharacter($lockedCharacter);
            $this->participant($combat, CombatSide::PLAYERS, 1, CombatParticipantType::CHARACTER, $lockedCharacter->id, $user->id, $characterSnapshot);
            foreach ($selected as $index => $monster) {
                $this->participant($combat, CombatSide::ENEMIES, $index + 1, CombatParticipantType::MONSTER, $monster->id, null, $this->snapshots->forMonster($monster, $index + 1));
            }

            $participants = CombatParticipant::where('combat_session_id', $combat->id)->orderBy('id')->lockForUpdate()->get();
            $order = $this->states->initialOrder($participants);
            $byIdentifier = $participants->keyBy('source_identifier');
            foreach ($order as $index => $identifier) {
                $participant = $byIdentifier->get($identifier);
                if (!$participant) throw new InvalidArgumentException('Initiative participant is missing.');
                $participant->initiative_position = $index + 1;
                $participant->save();
            }
            $current = $byIdentifier->get($order[0]);
            $combat->current_participant_id = $current->id;
            $combat->status = $current->participant_type === CombatParticipantType::CHARACTER ? ManualCombatStatus::WAITING_PLAYER : ManualCombatStatus::ACTIVE;
            $combat->lock_version = 1;
            $combat->save();

            $this->events->append($combat, ManualCombatEventType::COMBAT_STARTED, 1, null, ['version' => 1]);
            $this->events->append($combat, ManualCombatEventType::ROUND_STARTED, 1, null, ['version' => 1, 'round' => 1]);
            $this->events->append($combat, ManualCombatEventType::TURN_STARTED, 1, $current, ['version' => 1, 'participant_id' => (int) $current->id]);
            $this->turns->advanceAutomaticLocked($combat, $participants);

            return $this->read->read($user, $combat->fresh());
        }, 3);
    }

    private function participant(CombatSession $combat, $team, $position, $type, $sourceId, $ownerUserId, array $snapshot)
    {
        return CombatParticipant::create([
            'combat_session_id' => $combat->id,
            'team' => $team,
            'position' => $position,
            'participant_type' => $type,
            'source_id' => $sourceId,
            'owner_user_id' => $ownerUserId,
            'display_name' => $snapshot['name'],
            'source_identifier' => $snapshot['identifier'],
            'current_hp' => $snapshot['max_hp'],
            'max_hp' => $snapshot['max_hp'],
            'status' => CombatParticipantStatus::ALIVE,
            'stats_snapshot' => $snapshot['stats'],
        ]);
    }
}
