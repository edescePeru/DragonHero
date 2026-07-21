<?php
namespace App\Domain\Combat\Manual;
use App\Models\CombatSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
final class ManualCombatExpirationService
{
    private $policy;private $termination;
    public function __construct(ManualCombatExpirationPolicy $policy,ManualCombatTerminationService $termination){$this->policy=$policy;$this->termination=$termination;}
    public function expireIfNeededLocked(CombatSession $combat,CarbonImmutable $now=null)
    {
        if(DB::transactionLevel()<1)throw new RuntimeException('Active transaction required.');
        if(!$this->policy->canExpire($combat))return false;
        $now=$now?:CarbonImmutable::now();$expiresAt=$this->policy->expiresAt($combat);
        if($expiresAt===null||$now->lt($expiresAt))return false;
        $this->termination->expireLocked($combat,$now,$this->policy->minutes());return true;
    }
}
