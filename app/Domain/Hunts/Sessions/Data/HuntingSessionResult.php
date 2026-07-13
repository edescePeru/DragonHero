<?php
namespace App\Domain\Hunts\Sessions\Data;final class HuntingSessionResult{private $data;public function __construct(array $data){$this->data=$data;}public function toArray(){return array_merge([],$this->data);}public function id(){return$this->data['session_id'];}public function status(){return$this->data['status'];}}
