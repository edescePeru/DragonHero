<?php
namespace App\Domain\Hunts\Rewards\Claim\Data;
final class HuntRewardClaimResult{private $data;public function __construct(array $data){$this->data=$data;}public function toArray(){return array_merge([],$this->data);}public function claimedRewardsCount(){return$this->data['claimed_rewards_count'];}public function claimedTotalQuantity(){return$this->data['claimed_total_quantity'];}}
