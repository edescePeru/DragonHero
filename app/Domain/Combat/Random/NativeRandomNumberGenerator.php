<?php
namespace App\Domain\Combat\Random;
final class NativeRandomNumberGenerator implements RandomNumberGenerator { public function integer($minimum,$maximum){return random_int((int)$minimum,(int)$maximum);} }
