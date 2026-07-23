<?php
namespace App\Domain\Inventory\Instances\Rarity;
final class ItemRarityVisualPresentation
{
    private $v;public function __construct(array $v){$this->v=$v;}public function borderColorHex(){return$this->v['border_color_hex'];}public function borderRgb(){return$this->v['border_rgb'];}public function borderOpacityBasisPoints(){return$this->v['border_opacity_basis_points'];}public function borderOpacityCss(){return$this->v['border_opacity_css'];}public function borderWidthPx(){return$this->v['border_width_px'];}public function innerGlowColorHex(){return$this->v['inner_glow_color_hex'];}public function innerGlowRgb(){return$this->v['inner_glow_rgb'];}public function innerGlowOpacityBasisPoints(){return$this->v['inner_glow_opacity_basis_points'];}public function innerGlowOpacityCss(){return$this->v['inner_glow_opacity_css'];}public function innerGlowBlurPx(){return$this->v['inner_glow_blur_px'];}public function innerGlowSpreadPx(){return$this->v['inner_glow_spread_px'];}
    public function cssVariables(){return['--rarity-border-rgb'=>$this->borderRgb(),'--rarity-border-opacity'=>$this->borderOpacityCss(),'--rarity-border-width'=>$this->borderWidthPx().'px','--rarity-glow-rgb'=>$this->innerGlowRgb(),'--rarity-glow-opacity'=>$this->innerGlowOpacityCss(),'--rarity-glow-blur'=>$this->innerGlowBlurPx().'px','--rarity-glow-spread'=>$this->innerGlowSpreadPx().'px'];}
    public function inlineStyle(){$p=[];foreach($this->cssVariables()as$k=>$v)$p[]=$k.':'.$v;return implode(';',$p);}public function toArray(){return$this->v;}
}
