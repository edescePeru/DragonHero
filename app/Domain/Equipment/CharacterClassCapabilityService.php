<?php
namespace App\Domain\Equipment;use App\Models\CharacterClass;
final class CharacterClassCapabilityService{public function canDualWield(CharacterClass $class=null){return$class?(bool)$class->can_dual_wield:false;}}
