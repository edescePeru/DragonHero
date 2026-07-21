<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Combat\Manual\Data\ManualCombatState;
use App\Domain\Hunts\Sessions\HuntingSessionService;
use App\Models\Character;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ManualCombatEntryService
{
    private $hunting;
    private $creation;
    private $expiration;
    private $read;

    public function __construct(HuntingSessionService $hunting, ManualCombatCreationService $creation, ManualCombatExpirationService $expiration, ManualCombatReadService $read)
    {
        $this->hunting = $hunting;
        $this->creation = $creation;
        $this->expiration = $expiration;
        $this->read = $read;
    }

    public function enter(User $user, Character $character, Zone $zone)
    {
        return DB::transaction(function () use ($user, $character, $zone) {
            $lockedCharacter = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedCharacter->user_id !== (int) $user->id) throw new AuthorizationException();

            $active = CombatSession::where('active_slot', $lockedCharacter->id)->lockForUpdate()->first();
            if ($active) {
                $this->expiration->expireIfNeededLocked($active);
                if ($active->active_slot !== null) {
                    return ['state' => $this->read->read($user, $active), 'reused_other_zone' => (int) $active->zone_id !== (int) $zone->id];
                }
            }

            $running = HuntingSession::where('character_id', $lockedCharacter->id)->where('status', 'running')->lockForUpdate()->get();
            foreach ($running as $session) $this->hunting->stop($lockedCharacter, $session);

            $hunting = HuntingSession::findOrFail($this->hunting->start($lockedCharacter, $zone)->id());
            return ['state' => $this->creation->create($user, $lockedCharacter, $hunting), 'reused_other_zone' => false];
        }, 3);
    }
}
