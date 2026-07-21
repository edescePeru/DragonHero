<?php

namespace App\Domain\Combat\Manual;

use App\Domain\Hunts\Sessions\HuntingSessionStatus;
use App\Domain\Hunts\Sessions\HuntingSessionStopReason;
use App\Models\CombatSession;
use App\Models\HuntingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ManualCombatHuntingSessionLifecycleService
{
    public function stopRelatedSessionLocked(CombatSession $combat, $terminalStatus, CarbonImmutable $now)
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if (!in_array($terminalStatus, ManualCombatStatus::terminalValues(), true) || $combat->status !== $terminalStatus) throw new RuntimeException('A terminal CombatSession is required.');
        if ($combat->hunting_session_id === null) throw new RuntimeException('Manual combat has no related HuntingSession.');

        $session = HuntingSession::whereKey($combat->hunting_session_id)->lockForUpdate()->firstOrFail();
        if ((int) $session->id !== (int) $combat->hunting_session_id || (int) $session->character_id !== (int) $combat->character_id || (int) $session->zone_id !== (int) $combat->zone_id) {
            throw new RuntimeException('Manual combat HuntingSession relationship is inconsistent.');
        }

        $hasAnotherActiveCombat = CombatSession::query()
            ->where('hunting_session_id', $session->id)
            ->where('id', '<>', $combat->id)
            ->where(function ($query) {
                $query->whereNotNull('active_slot')->orWhereIn('status', ManualCombatStatus::activeValues());
            })
            ->exists();
        if ($hasAnotherActiveCombat) throw new RuntimeException('HuntingSession is related to another active CombatSession.');

        if ($session->status === HuntingSessionStatus::RUNNING) {
            $session->status = HuntingSessionStatus::STOPPED;
            $session->stop_reason = self::stopReasonFor($terminalStatus);
            $session->stopped_at = $now;
            $session->next_encounter_at = null;
            $session->save();
        }

        return $session;
    }

    public static function stopReasonFor($terminalStatus)
    {
        $reasons = [
            ManualCombatStatus::WON => HuntingSessionStopReason::MANUAL_COMBAT_WON,
            ManualCombatStatus::LOST => HuntingSessionStopReason::MANUAL_COMBAT_LOST,
            ManualCombatStatus::ABANDONED => HuntingSessionStopReason::MANUAL_COMBAT_ABANDONED,
            ManualCombatStatus::EXPIRED => HuntingSessionStopReason::MANUAL_COMBAT_EXPIRED,
        ];
        if (!isset($reasons[$terminalStatus])) throw new RuntimeException('Unsupported manual combat terminal status.');
        return $reasons[$terminalStatus];
    }
}
