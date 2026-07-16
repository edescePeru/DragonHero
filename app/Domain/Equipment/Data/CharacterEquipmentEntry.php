<?php
namespace App\Domain\Equipment\Data;final class CharacterEquipmentEntry{private $data;public function __construct(array $data){$this->data=$data;}public function slot(){return$this->data['slot'];}public function occupied(){return$this->data['occupied'];}public function toArray(){return array_merge([],$this->data);}}
