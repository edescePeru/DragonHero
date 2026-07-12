<?php
namespace App\Domain\Wallet\Data;
final class WalletBalance { private $characterId; private $balance; public function __construct($characterId,$balance){$this->characterId=(int)$characterId;$this->balance=(int)$balance;} public function characterId(){return $this->characterId;} public function balance(){return $this->balance;} }
