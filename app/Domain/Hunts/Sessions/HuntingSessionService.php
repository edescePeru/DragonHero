<?php
namespace App\Domain\Hunts\Sessions;

use App\Domain\Combat\CombatResultStatus;
use App\Domain\Hunts\Data\HuntResult;
use App\Domain\Hunts\Exceptions\InvalidHuntingZoneException;
use App\Domain\Hunts\Exceptions\NoActiveEncounterSizeException;
use App\Domain\Hunts\Exceptions\NoEligibleMonsterException;
use App\Domain\Hunts\HuntService;
use App\Domain\Hunts\Playback\HuntingPlaybackCalculator;
use App\Domain\Hunts\Rewards\HuntRewardService;
use App\Domain\Hunts\Sessions\Data\HuntExecutionContext;
use App\Domain\Hunts\Sessions\Data\HuntingSessionResult;
use App\Domain\Hunts\Sessions\Data\HuntingSessionTickResult;
use App\Domain\Hunts\Sessions\Exceptions\ActiveHuntingSessionExistsException;
use App\Domain\Hunts\Sessions\Exceptions\HuntingSessionOwnershipException;
use App\Domain\Combat\Manual\ManualCombatHuntingSessionLifecycleService;
use App\Domain\Combat\Manual\ManualCombatStatus;
use App\Domain\Inventory\Capacity\Exceptions\InsufficientPendingRewardCapacityException;
use App\Domain\Inventory\Capacity\PendingRewardCapacityService;
use App\Domain\Media\MediaAssetType;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Models\Character;
use App\Models\CombatSession;
use App\Models\Hunt;
use App\Models\HuntCombatEvent;
use App\Models\HuntingSession;
use App\Models\Zone;
use App\Models\ZoneEncounterSize;
use App\Models\ZoneMonster;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class HuntingSessionService
{
    private $hunts;
    private $rewards;
    private $capacity;
    private $generatedReward;
    private $resultCapacity;
    private $playback;
    private $initialHistory;
    private $presentation;
    private $manualCombatSessions;

    public function __construct(HuntService $hunts, HuntRewardService $rewards, PendingRewardCapacityService $capacity, HuntingPlaybackCalculator $playback, HuntingSessionPresentationService $presentation, ManualCombatHuntingSessionLifecycleService $manualCombatSessions)
    {
        $this->hunts = $hunts;
        $this->rewards = $rewards;
        $this->capacity = $capacity;
        $this->playback = $playback;
        $this->presentation = $presentation;
        $this->manualCombatSessions = $manualCombatSessions;
    }

    public function start(Character $character, Zone $zone): HuntingSessionResult
    {
        return DB::transaction(function () use ($character, $zone) {
            $now = CarbonImmutable::now();
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $running = HuntingSession::where('character_id', $lockedCharacter->id)->where('status', HuntingSessionStatus::RUNNING)->lockForUpdate()->get();
            foreach ($running as $old) {
                if ($this->recoverTerminalManualCombatSession($old, $now)) {
                    continue;
                } elseif ($this->heartbeatExpired($old, $now)) {
                    $this->stopLocked($old, HuntingSessionStopReason::HEARTBEAT_TIMEOUT, $now);
                } else {
                    throw new ActiveHuntingSessionExistsException('El personaje ya tiene una sesión activa.');
                }
            }
            $lockedZone = Zone::whereKey($zone->id)->firstOrFail();
            if (!$this->characterAvailable($lockedCharacter) || !$this->zoneAvailable($lockedZone, $lockedCharacter)) {
                throw new InvalidHuntingZoneException('La zona no está disponible para cacería conectada.');
            }
            $this->resultCapacity = $this->capacity->locked($lockedCharacter, $now);
            if (!$this->resultCapacity->huntingCanContinue()) {
                throw new InsufficientPendingRewardCapacityException($this->resultCapacity);
            }
            $session = HuntingSession::create(['character_id' => $lockedCharacter->id, 'zone_id' => $lockedZone->id, 'mode' => HuntingSessionMode::CONNECTED, 'status' => HuntingSessionStatus::RUNNING, 'stop_reason' => null, 'consecutive_defeats' => 0, 'hunts_count' => 0, 'victories_count' => 0, 'defeats_count' => 0, 'draws_count' => 0, 'next_encounter_at' => $now, 'last_heartbeat_at' => $now, 'started_at' => $now, 'stopped_at' => null]);
            return $this->result($session, $now, null, null);
        }, 3);
    }

    public function show(Character $character, HuntingSession $session): HuntingSessionResult
    {
        $this->assertOwnership($character, $session);
        $now = CarbonImmutable::now();
        $this->resultCapacity = $this->capacity->snapshot($character, $now);
        $this->initialHistory = $this->initialHistory($session);
        $latest = count($this->initialHistory['hunts']) > 0 ? $this->initialHistory['hunts'][0] : null;
        return $this->result($session, $now, $latest, null);
    }

    public function tick(Character $character, HuntingSession $session): HuntingSessionTickResult
    {
        return DB::transaction(function () use ($character, $session) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $lockedSession = HuntingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertOwnership($lockedCharacter, $lockedSession);
            $now = CarbonImmutable::now();
            if ($lockedSession->status === HuntingSessionStatus::STOPPED) return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            if ($this->heartbeatExpired($lockedSession, $now)) {
                $this->stopLocked($lockedSession, HuntingSessionStopReason::HEARTBEAT_TIMEOUT, $now);
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            if (!$this->characterAvailable($lockedCharacter)) {
                $this->stopLocked($lockedSession, HuntingSessionStopReason::CHARACTER_UNAVAILABLE, $now);
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            $zone = Zone::whereKey($lockedSession->zone_id)->first();
            if (!$zone || !$this->basicZoneAvailable($zone)) {
                $this->stopLocked($lockedSession, HuntingSessionStopReason::ZONE_UNAVAILABLE, $now);
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            $lockedSession->last_heartbeat_at = $now;
            $lockedSession->save();
            if (CombatSession::where('active_slot', $lockedCharacter->id)->exists()) {
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            if ($lockedSession->next_encounter_at && $now->lt(CarbonImmutable::instance($lockedSession->next_encounter_at))) {
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            $this->resultCapacity = $this->capacity->locked($lockedCharacter, $now);
            if (!$this->resultCapacity->huntingCanContinue()) {
                $this->stopLocked($lockedSession, HuntingSessionStopReason::PENDING_INVENTORY_CAPACITY, $now);
                return $this->tickWithoutHunt($lockedCharacter, $lockedSession, $now);
            }
            try {
                $hunt = $this->hunts->startForLockedSession($lockedCharacter, $zone, new HuntExecutionContext($lockedSession->id));
            } catch (InvalidHuntingZoneException $exception) {
                return $this->stopForZone($lockedCharacter, $lockedSession, $now);
            } catch (NoEligibleMonsterException $exception) {
                return $this->stopForZone($lockedCharacter, $lockedSession, $now);
            } catch (NoActiveEncounterSizeException $exception) {
                return $this->stopForZone($lockedCharacter, $lockedSession, $now);
            }
            $victory = $this->applyHunt($lockedSession, $hunt, $now);
            if ($victory) {
                $this->resultCapacity = $this->capacity->locked($lockedCharacter, $now);
                if (!$this->resultCapacity->huntingCanContinue()) $this->stopLocked($lockedSession, HuntingSessionStopReason::PENDING_INVENTORY_CAPACITY, $now);
            }
            $lockedSession->save();
            $processedHunt = $this->latestHunt($lockedSession);
            return new HuntingSessionTickResult($this->result($lockedSession, $now, $processedHunt, $processedHunt)->toArray());
        }, 3);
    }

    public function stop(Character $character, HuntingSession $session): HuntingSessionResult
    {
        return DB::transaction(function () use ($character, $session) {
            $now = CarbonImmutable::now();
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            $lockedSession = HuntingSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertOwnership($lockedCharacter, $lockedSession);
            if ($lockedSession->status === HuntingSessionStatus::RUNNING) $this->stopLocked($lockedSession, HuntingSessionStopReason::USER_STOPPED, $now);
            $this->resultCapacity = $this->capacity->locked($lockedCharacter, $now);
            return $this->result($lockedSession, $now, $this->latestHunt($lockedSession), null);
        }, 3);
    }

    private function tickWithoutHunt(Character $character, HuntingSession $session, CarbonImmutable $now)
    {
        if ($this->resultCapacity === null) $this->resultCapacity = $this->capacity->locked($character, $now);
        return new HuntingSessionTickResult($this->result($session, $now, $this->latestHunt($session), null)->toArray());
    }

    private function stopForZone(Character $character, HuntingSession $session, CarbonImmutable $now)
    {
        $this->stopLocked($session, HuntingSessionStopReason::ZONE_UNAVAILABLE, $now);
        return $this->tickWithoutHunt($character, $session, $now);
    }

    private function applyHunt(HuntingSession $session, HuntResult $hunt, CarbonImmutable $now)
    {
        $session->hunts_count++;
        $victory = false;
        if ($hunt->status() === CombatResultStatus::CHARACTER_VICTORY) {
            $this->generatedReward = $this->rewards->generatePendingForLockedHunt($hunt->huntId(), $session->id);
            $session->victories_count++;
            $session->consecutive_defeats = 0;
            $session->next_encounter_at = $now->addSeconds($this->effectiveWaitSeconds(HuntingSessionLimits::VICTORY_COOLDOWN_SECONDS, $hunt->playbackDurationMs()));
            $victory = true;
        } elseif ($hunt->status() === CombatResultStatus::MONSTER_VICTORY) {
            $session->defeats_count++;
            $this->applyNonWin($session, $hunt, $now);
        } elseif ($hunt->status() === CombatResultStatus::DRAW) {
            $session->draws_count++;
            $this->applyNonWin($session, $hunt, $now);
        } else {
            throw new \RuntimeException('Unexpected Hunt status.');
        }
        if ($session->hunts_count !== $session->victories_count + $session->defeats_count + $session->draws_count) throw new \RuntimeException('Hunting session counter invariant failed.');
        return $victory;
    }

    private function applyNonWin(HuntingSession $session, HuntResult $hunt, CarbonImmutable $now)
    {
        $session->consecutive_defeats++;
        if ($session->consecutive_defeats >= HuntingSessionLimits::MAX_CONSECUTIVE_DEFEATS) {
            $this->stopLocked($session, HuntingSessionStopReason::CONSECUTIVE_DEFEATS, $now);
            return;
        }
        $seconds = $session->consecutive_defeats === 1 ? HuntingSessionLimits::FIRST_DEFEAT_COOLDOWN_SECONDS : HuntingSessionLimits::SECOND_DEFEAT_COOLDOWN_SECONDS;
        $session->next_encounter_at = $now->addSeconds($this->effectiveWaitSeconds($seconds, $hunt->playbackDurationMs()));
    }

    private function effectiveWaitSeconds($cooldownSeconds, $playbackMilliseconds)
    {
        return max((int) $cooldownSeconds, $this->playback->millisecondsToCeilingSeconds((int) $playbackMilliseconds));
    }

    private function heartbeatExpired(HuntingSession $session, CarbonImmutable $now){return $now->gt(CarbonImmutable::instance($session->last_heartbeat_at)->addSeconds(HuntingSessionLimits::CONNECTED_HEARTBEAT_TIMEOUT_SECONDS));}
    private function recoverTerminalManualCombatSession(HuntingSession $session, CarbonImmutable $now)
    {
        $linked = CombatSession::where('hunting_session_id', $session->id)->orderBy('id')->get();
        if ($linked->isEmpty()) return false;
        if ($linked->contains(function ($combat) { return $combat->active_slot !== null || !in_array($combat->status, ManualCombatStatus::terminalValues(), true); })) return false;
        $terminal = $linked->last();
        $this->manualCombatSessions->stopRelatedSessionLocked($terminal, $terminal->status, $now);
        return true;
    }
    private function stopLocked(HuntingSession $session, $reason, CarbonImmutable $now){$session->status=HuntingSessionStatus::STOPPED;$session->stop_reason=$reason;$session->stopped_at=$now;$session->next_encounter_at=null;$session->save();}
    private function characterAvailable(Character $character){return $character->status === 'active';}
    private function basicZoneAvailable(Zone $zone){return $zone->status === CatalogStatus::ACTIVE && (bool) $zone->allows_hunting;}
    private function zoneAvailable(Zone $zone, Character $character){if(!$this->basicZoneAvailable($zone))return false;if(!ZoneEncounterSize::where('zone_id',$zone->id)->where('is_active',true)->exists())return false;return ZoneMonster::where('zone_id',$zone->id)->where('status',CatalogStatus::ACTIVE)->where('minimum_character_level','<=',$character->level)->whereHas('monster',function($query){$query->where('status',CatalogStatus::ACTIVE);})->exists();}
    private function assertOwnership(Character $character, HuntingSession $session){if((int)$session->character_id !== (int)$character->id)throw new HuntingSessionOwnershipException('Session does not belong to Character.');}

    private function latestHunt(HuntingSession $session)
    {
        $hunt = $session->hunts()->with(['enemies.monster.mediaAssets','combatEvents','reward.items'])->orderByDesc('id')->first();
        if (!$hunt) return null;
        return $this->serializeHunt($hunt);
    }

    private function initialHistory(HuntingSession $session)
    {
        $query = $session->hunts()->orderByDesc('id');
        $totalHunts = (clone $query)->count();
        $hunts = $query->with(['enemies.monster.mediaAssets','reward.items'])->limit(HuntingSessionLogLimits::INITIAL_SESSION_LOG_HUNTS)->get();
        $huntIds = $hunts->pluck('id');
        $totalEvents = $huntIds->isEmpty() ? 0 : HuntCombatEvent::whereIn('hunt_id', $huntIds)->count();
        $events = $huntIds->isEmpty() ? collect() : HuntCombatEvent::query()->select('hunt_combat_events.*')->join('hunts','hunts.id','=','hunt_combat_events.hunt_id')->whereIn('hunt_combat_events.hunt_id',$huntIds)->orderByDesc('hunts.id')->orderByDesc('hunt_combat_events.sequence')->limit(HuntingSessionLogLimits::INITIAL_SESSION_LOG_EVENTS)->get()->groupBy('hunt_id');
        $serialized = $hunts->map(function ($hunt, $index) use ($events, $totalHunts) {
            $hunt->setRelation('combatEvents', collect($events->get($hunt->id, collect()))->sortBy('sequence')->values());
            $data=$this->serializeHunt($hunt);$data['encounter_number']=$totalHunts-$index;return $data;
        })->all();
        return ['hunts'=>$serialized,'has_more'=>$totalHunts>$hunts->count()||$totalEvents>HuntingSessionLogLimits::INITIAL_SESSION_LOG_EVENTS,'hunt_limit'=>HuntingSessionLogLimits::INITIAL_SESSION_LOG_HUNTS,'event_limit'=>HuntingSessionLogLimits::INITIAL_SESSION_LOG_EVENTS];
    }

    private function serializeHunt(Hunt $hunt)
    {
        $participants=[['identifier'=>'character:'.$hunt->character_id,'side'=>'players','display_name'=>$hunt->character_name,'initial_health'=>$hunt->character_health_before,'final_health'=>$hunt->character_health_after,'status'=>$hunt->character_health_after>0?'alive':'defeated']];
        $hunt->loadMissing('enemies.monster.mediaAssets');
        foreach($hunt->enemies as $enemy){$visual=$this->monsterVisual($enemy->monster);$participants[]=['identifier'=>$enemy->instance_identifier,'side'=>'enemies','display_name'=>$enemy->monster_name_snapshot.($hunt->enemy_count>1?' '.$enemy->position:''),'initial_health'=>$enemy->initial_health,'final_health'=>$enemy->final_health,'status'=>$enemy->status,'monster_id'=>(int)$enemy->monster_id,'image_url'=>$visual['url'],'image_type'=>$visual['type']];}
        $events=$hunt->combatEvents->map(function($event){return $event->only(['sequence','round','actor_identifier','target_identifier','event_type','did_hit','hit_probability','hit_roll','critical_probability','critical_roll','is_critical','damage','healing','target_health_before','target_health_after','playback_offset_ms','playback_duration_ms']);})->all();
        $historicalReward=null;if($hunt->reward){$items=$hunt->reward->items->map(function($item){return['item_id'=>$item->item_id,'item_name'=>$item->item_name_snapshot,'quantity'=>$item->quantity];})->all();$historicalReward=['hunt_id'=>$hunt->id,'item_lines_count'=>count($items),'items'=>$items];}
        return ['hunt_id'=>$hunt->id,'status'=>$hunt->status,'rounds_count'=>$hunt->rounds_count,'enemy_count'=>$hunt->enemy_count,'character_health_before'=>$hunt->character_health_before,'character_health_after'=>$hunt->character_health_after,'resolved_at'=>$hunt->resolved_at->toIso8601String(),'combat_events_duration_ms'=>$hunt->combat_events_duration_ms,'result_reveal_duration_ms'=>$hunt->result_reveal_duration_ms,'loot_reveal_duration_ms'=>$hunt->loot_reveal_duration_ms,'playback_duration_ms'=>$hunt->playback_duration_ms,'playback_speed_multiplier'=>$hunt->playback_speed_multiplier,'participants'=>$participants,'events'=>$events,'historical_reward'=>$historicalReward,'enemies'=>$hunt->enemies->map(function($enemy){return ['position'=>$enemy->position,'identifier'=>$enemy->instance_identifier,'monster_name'=>$enemy->monster_name_snapshot,'initial_health'=>$enemy->initial_health,'final_health'=>$enemy->final_health,'status'=>$enemy->status];})->all()];
    }

    private function result(HuntingSession $session, CarbonImmutable $now, $latestHunt, $processedHunt)
    {
        $next = $session->next_encounter_at ? CarbonImmutable::instance($session->next_encounter_at) : null;
        $generatedReward = $this->generatedReward ? $this->generatedReward->toArray() : null;
        $this->generatedReward = null;
        if ($processedHunt === null) $generatedReward = null;
        if ($processedHunt !== null) $processedHunt['encounter_number'] = (int) $session->hunts_count;
        if ($generatedReward !== null && (int)$generatedReward['hunt_id'] !== (int)$processedHunt['hunt_id']) throw new \RuntimeException('Generated reward does not match processed Hunt.');
        $capacity = $this->resultCapacity ? $this->resultCapacity->toArray() : null;
        $this->resultCapacity = null;
        $global=$this->presentation->decoratePendingSummary($this->rewards->summaryPendingForCharacter((object)['id'=>$session->character_id]));
        $data=['session_id'=>$session->id,'status'=>$session->status,'stop_reason'=>$session->stop_reason,'server_time'=>$now->toIso8601String(),'next_encounter_at'=>$next?$next->toIso8601String():null,'seconds_until_next_encounter'=>$next?max(0,$now->diffInSeconds($next,false)):null,'consecutive_defeats'=>$session->consecutive_defeats,'hunts_count'=>$session->hunts_count,'victories_count'=>$session->victories_count,'defeats_count'=>$session->defeats_count,'draws_count'=>$session->draws_count,'latest_hunt'=>$latestHunt,'processed_hunt'=>$processedHunt,'generated_reward'=>$generatedReward,'session_pending_rewards_summary'=>$this->rewards->summary($session->id),'character_pending_rewards_summary'=>$global,'inventory_capacity'=>$capacity];
        if($this->initialHistory!==null){$data['session_hunt_history']=$this->initialHistory;$this->initialHistory=null;}
        return new HuntingSessionResult($data);
    }

    private function monsterVisual($monster){if(!$monster||!$monster->relationLoaded('mediaAssets'))return['url'=>null,'type'=>null];foreach([MediaAssetType::SPRITE_IDLE,MediaAssetType::PORTRAIT,MediaAssetType::IMAGE]as$type){$asset=$monster->mediaAssets->first(function($candidate)use($type){return$candidate->asset_type===$type&&(bool)$candidate->is_primary;});if($asset)return['url'=>$asset->url(),'type'=>$type];}return['url'=>null,'type'=>null];}
}
