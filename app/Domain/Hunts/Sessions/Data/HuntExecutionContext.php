<?php
namespace App\Domain\Hunts\Sessions\Data;use InvalidArgumentException;final class HuntExecutionContext{private $id;public function __construct($id){if((int)$id<1)throw new InvalidArgumentException('Invalid hunting session context.');$this->id=(int)$id;}public function huntingSessionId(){return$this->id;}}
