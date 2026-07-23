<?php
namespace App\Domain\Inventory\Instances\Rarity;
use App\Domain\Probability\PercentageBasisPointsConverter;use App\Models\ItemRarity;use InvalidArgumentException;
final class ItemRarityVisualStyleResolver
{
    private $validator;private $percentages;public function __construct(ItemRarityVisualConfiguration $validator,PercentageBasisPointsConverter $percentages){$this->validator=$validator;$this->percentages=$percentages;}
    public function resolve(ItemRarity $rarity){$v=$rarity->only(['border_color_hex','border_opacity_basis_points','border_width_px','inner_glow_color_hex','inner_glow_opacity_basis_points','inner_glow_blur_px','inner_glow_spread_px']);try{$v=$this->validator->normalize($v);}catch(InvalidArgumentException $e){$v=$this->fallback($rarity->visual_style);}$v['border_rgb']=$this->rgb($v['border_color_hex']);$v['inner_glow_rgb']=$this->rgb($v['inner_glow_color_hex']);$v['border_opacity_css']=$this->percentages->toCssOpacity($v['border_opacity_basis_points']);$v['inner_glow_opacity_css']=$this->percentages->toCssOpacity($v['inner_glow_opacity_basis_points']);return new ItemRarityVisualPresentation($v);}
    private function rgb($hex){return hexdec(substr($hex,1,2)).', '.hexdec(substr($hex,3,2)).', '.hexdec(substr($hex,5,2));}
    private function fallback($style){$all=['neutral'=>['#A3A3A3',10000,1,'#A3A3A3',0,0,0],'blue'=>['#2563EB',10000,2,'#2563EB',2000,16,1],'purple'=>['#7E22CE',10000,2,'#7E22CE',2800,18,1],'gold'=>['#B7791F',10000,2,'#B7791F',3500,20,2]];$v=isset($all[$style])?$all[$style]:$all['neutral'];return array_combine(['border_color_hex','border_opacity_basis_points','border_width_px','inner_glow_color_hex','inner_glow_opacity_basis_points','inner_glow_blur_px','inner_glow_spread_px'],$v);}
}
