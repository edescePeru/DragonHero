<?php
namespace App\Domain\Probability;
use InvalidArgumentException;
final class PercentageBasisPointsConverter
{
    public function toBasisPoints($percentage){if(!is_string($percentage)&&!is_int($percentage))throw new InvalidArgumentException('Percentage must be a decimal string.');$value=trim((string)$percentage);if(!preg_match('/^(0|[1-9][0-9]?|100)(?:\.([0-9]{1,2}))?$/D',$value,$m))throw new InvalidArgumentException('Percentage must be between 0 and 100 with at most two decimals.');$fraction=isset($m[2])?(int)str_pad($m[2],2,'0'):0;if((int)$m[1]===100&&$fraction!==0)throw new InvalidArgumentException('Percentage cannot exceed 100.');return((int)$m[1]*100)+$fraction;}
    public function toPercentageString($bp){$this->assert($bp);$whole=intdiv($bp,100);$fraction=$bp%100;return$fraction===0?(string)$whole:$whole.'.'.rtrim(str_pad((string)$fraction,2,'0',STR_PAD_LEFT),'0');}
    public function toCssOpacity($bp){$this->assert($bp);if($bp===0||$bp===10000)return$bp===0?'0':'1';return'0.'.rtrim(str_pad((string)$bp,4,'0',STR_PAD_LEFT),'0');}
    private function assert($bp){if(!is_int($bp)||$bp<0||$bp>10000)throw new InvalidArgumentException('Invalid percentage basis points.');}
}
