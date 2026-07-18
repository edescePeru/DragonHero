<?php
namespace App\Domain\Equipment\Exceptions;use App\Domain\Equipment\Data\EquipmentLoadoutResult;use RuntimeException;
final class EquipmentLoadoutConflictException extends RuntimeException{private $result;public function __construct(EquipmentLoadoutResult $result){parent::__construct($result->reason());$this->result=$result;}public function result(){return$this->result;}}
