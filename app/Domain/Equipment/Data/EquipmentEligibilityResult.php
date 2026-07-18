<?php
namespace App\Domain\Equipment\Data;
final class EquipmentEligibilityResult{private $data;public function __construct(array $data){$this->data=$data;}public function eligible(){return(bool)$this->data['eligible'];}public function reasonCodes(){return$this->data['reason_codes'];}public function message(){return$this->data['message'];}public function toArray(){return$this->data;}}
