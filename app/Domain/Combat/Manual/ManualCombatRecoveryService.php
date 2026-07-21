<?php
namespace App\Domain\Combat\Manual;
use App\Models\CombatActionRequest;
use App\Models\CombatParticipant;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class ManualCombatRecoveryService
{
    private $turns;
    public function __construct(ManualCombatTurnService $turns){$this->turns=$turns;}
    public function recoverLocked(CombatSession $combat)
    {
        if(DB::transactionLevel()<1)throw new RuntimeException('Active transaction required.');
        if($combat->status!==ManualCombatStatus::ACTIVE)return false;
        if(CombatActionRequest::where('combat_session_id',$combat->id)->where('status',ManualCombatActionRequestStatus::PROCESSING)->lockForUpdate()->exists())return false;
        $participants=CombatParticipant::where('combat_session_id',$combat->id)->orderBy('id')->lockForUpdate()->get();
        $this->turns->advanceAutomaticLocked($combat,$participants);
        $combat->lock_version=(int)$combat->lock_version+1;$combat->last_action_at=CarbonImmutable::now();$combat->save();return true;
    }
}
