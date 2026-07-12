<?php
namespace App\Domain\Random;
interface RandomNumberGenerator { public function randomInt(int $minimum,int $maximum): int; }
