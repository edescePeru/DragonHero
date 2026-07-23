<?php
namespace App\Domain\Inventory\Instances\Rarity;
use App\Domain\Inventory\Instances\ItemRarityCode;use App\Models\ItemRarityDropSetting;use App\Models\User;use Illuminate\Support\Facades\DB;use InvalidArgumentException;
final class ItemRarityDropConfigurationService
{
    public function current():ItemRarityDropConfiguration{return$this->fromModel(ItemRarityDropSetting::whereKey(1)->firstOrFail());}
    public function update(User $admin,array $values,int $version):ItemRarityDropSetting{return DB::transaction(function()use($admin,$values,$version){$setting=ItemRarityDropSetting::whereKey(1)->lockForUpdate()->firstOrFail();if($version!==(int)$setting->version)throw new InvalidArgumentException('La configuración fue modificada por otro administrador.');$config=new ItemRarityDropConfiguration($values,$version);foreach($config->probabilities() as $code=>$ppm)$setting->{$code.'_probability_ppm'}=$ppm;$setting->version=$version+1;$setting->updated_by=$admin->id;$setting->save();return$setting;},3);}
    private function fromModel($s):ItemRarityDropConfiguration{$p=[];foreach(ItemRarityCode::values() as $code)$p[$code]=(int)$s->{$code.'_probability_ppm'};return new ItemRarityDropConfiguration($p,(int)$s->version);}
}
