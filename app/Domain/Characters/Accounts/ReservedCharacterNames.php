<?php
namespace App\Domain\Characters\Accounts;
final class ReservedCharacterNames
{
    private $normalizer;
    public function __construct(CharacterNameNormalizer $normalizer){$this->normalizer=$normalizer;}
    public function contains($name){return in_array($this->normalizer->normalized($name),['admin','administrator','administrador','moderator','moderador','gamemaster','game master','gm','system','sistema','support','soporte','dragonhero'],true);}
}
