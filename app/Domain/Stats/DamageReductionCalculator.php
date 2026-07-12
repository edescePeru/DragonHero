<?php
namespace App\Domain\Stats;
final class DamageReductionCalculator { const CONSTANT_VALUE=100; const MAX_RATE=75.0; public function calculate($defense){$defense=(int)$defense;if($defense<=0)return 0.0;return min(($defense/($defense+self::CONSTANT_VALUE))*100,self::MAX_RATE);} }
