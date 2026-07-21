<?php
namespace App\Domain\Hunts\Rewards;
use App\Domain\Hunts\Rewards\Data\HuntRewardValues;
use App\Domain\Random\RandomNumberGenerator;
final class HuntRewardValueGenerator{private $monsterValues;public function __construct(RandomNumberGenerator $random){$this->monsterValues=new MonsterRewardValueGenerator($random);}public function generate($enemies,$monsters):HuntRewardValues{$gold=0;$experience=0;foreach($enemies->sortBy('position') as $enemy){$monster=$monsters->get($enemy->monster_id);if(!$monster)throw new \InvalidArgumentException('Monster catalog missing.');$values=$this->monsterValues->generate($monster);$rolled=$values->gold();$rewardExperience=$values->experience();if($rolled>PHP_INT_MAX-$gold||$rewardExperience>PHP_INT_MAX-$experience)throw new \InvalidArgumentException('Reward value overflow.');$gold+=$rolled;$experience+=$rewardExperience;}return new HuntRewardValues($gold,$experience);}}
