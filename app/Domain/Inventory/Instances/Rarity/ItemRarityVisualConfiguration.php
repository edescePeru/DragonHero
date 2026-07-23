<?php
namespace App\Domain\Inventory\Instances\Rarity;
use InvalidArgumentException;
final class ItemRarityVisualConfiguration
{
    public function validate(array $v){foreach(['border_color_hex','inner_glow_color_hex']as$f)if(!isset($v[$f])||!preg_match('/^#[0-9A-F]{6}$/D',(string)$v[$f]))throw new InvalidArgumentException('Invalid rarity visual color.');$this->integer($v,'border_opacity_basis_points',0,10000);$this->integer($v,'border_width_px',1,5);$this->integer($v,'inner_glow_opacity_basis_points',0,10000);$this->integer($v,'inner_glow_blur_px',0,40);$this->integer($v,'inner_glow_spread_px',0,20);}
    public function normalize(array $v){$v['border_color_hex']=strtoupper(trim((string)$v['border_color_hex']));$v['inner_glow_color_hex']=strtoupper(trim((string)$v['inner_glow_color_hex']));foreach(['border_opacity_basis_points','border_width_px','inner_glow_opacity_basis_points','inner_glow_blur_px','inner_glow_spread_px']as$f)$v[$f]=(int)$v[$f];$this->validate($v);return$v;}
    private function integer(array $v,$f,$min,$max){if(!array_key_exists($f,$v)||filter_var($v[$f],FILTER_VALIDATE_INT)===false||(int)$v[$f]<$min||(int)$v[$f]>$max)throw new InvalidArgumentException('Invalid rarity visual range.');}
}
