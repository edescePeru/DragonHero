<?php
namespace App\Domain\Characters\Templates;
final class CharacterTemplatePresentationGender
{
    const MALE='male'; const FEMALE='female'; const NEUTRAL='neutral';
    public static function all(){return [self::MALE,self::FEMALE,self::NEUTRAL];}
    public static function labels(){return [self::MALE=>'Hombre',self::FEMALE=>'Mujer',self::NEUTRAL=>'Neutral'];}
    private function __construct(){}
}
