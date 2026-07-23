<?php
namespace App\Domain\Combat;use App\Domain\Combat\Data\CombatMitigationConfig;use App\Models\CombatBalanceSetting;
final class CombatMitigationConfigProvider{private $loaded;public function current(){if($this->loaded)return$this->loaded;$s=CombatBalanceSetting::find(1);return$this->loaded=$s?new CombatMitigationConfig((int)$s->defense_reduction_cap_basis_points,(int)$s->absorb_damage_cap_basis_points,(int)$s->total_mitigation_cap_basis_points,(int)$s->minimum_damage):CombatMitigationConfig::defaults();}}
