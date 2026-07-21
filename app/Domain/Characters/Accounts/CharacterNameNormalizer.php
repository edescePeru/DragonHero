<?php
namespace App\Domain\Characters\Accounts;
final class CharacterNameNormalizer
{
    public function visible($name){$name=preg_replace('/\s+/u',' ',trim((string)$name));if(class_exists('Normalizer')){$name=\Normalizer::normalize($name,\Normalizer::FORM_C);}return $name;}
    public function normalized($name){return mb_strtolower($this->visible($name),'UTF-8');}
}
