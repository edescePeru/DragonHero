<?php
namespace App\Domain\Random;
use InvalidArgumentException;
final class NativeRandomNumberGenerator implements RandomNumberGenerator {public function randomInt(int $minimum,int $maximum):int{if($minimum>$maximum)throw new InvalidArgumentException('Minimum cannot exceed maximum.');return random_int($minimum,$maximum);}}
