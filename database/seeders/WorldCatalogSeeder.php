<?php
namespace Database\Seeders;
use App\Domain\WorldCatalog\CatalogStatus;
use App\Domain\WorldCatalog\ItemRarity;
use App\Domain\WorldCatalog\ItemType;
use App\Domain\WorldCatalog\TravelType;
use App\Domain\WorldCatalog\WorldCatalogValidator;
use App\Domain\WorldCatalog\ZoneType;
use App\Models\Item;
use App\Models\Monster;
use App\Models\Region;
use App\Models\World;
use App\Models\Zone;
use App\Models\ZoneConnection;
use App\Models\ZoneMonster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class WorldCatalogSeeder extends Seeder {
 public function run(){DB::transaction(function(){
  $v=app(WorldCatalogValidator::class); $active=CatalogStatus::ACTIVE;
  $world=World::updateOrCreate(['code'=>$v->normalizeCode('eldoria')],['name'=>'Eldoria','description'=>'Mundo medieval inicial del juego','status'=>$active,'sort_order'=>1]);
  $region=Region::updateOrCreate(['world_id'=>$world->id,'code'=>$v->normalizeCode('kingdom_of_valtheria')],['name'=>'Reino de Valtheria','description'=>'Región inicial de Eldoria','recommended_level_min'=>1,'recommended_level_max'=>20,'status'=>$active,'sort_order'=>1]);
  $zones=[]; foreach([
   ['dawn_village','Aldea del Alba',ZoneType::TOWN,1,1,true,false,1],
   ['grey_oak_forest','Bosque de Roblegris',ZoneType::FOREST,1,5,false,true,2],
   ['abandoned_mines','Minas Abandonadas',ZoneType::MINE,4,10,false,true,3]
  ] as $z){$v->assertZoneType($z[2]);$v->assertLevelRange($z[3],$z[4]);$zones[$z[0]]=Zone::updateOrCreate(['region_id'=>$region->id,'code'=>$v->normalizeCode($z[0])],['name'=>$z[1],'description'=>'Contenido inicial provisional','zone_type'=>$z[2],'recommended_level_min'=>$z[3],'recommended_level_max'=>$z[4],'is_safe'=>$z[5],'allows_hunting'=>$z[6],'status'=>$active,'sort_order'=>$z[7]]);}
  $items=[]; foreach([
   ['worn_leather','Cuero desgastado',ItemType::MATERIAL,ItemRarity::COMMON],['wolf_fang','Colmillo de lobo',ItemType::MATERIAL,ItemRarity::COMMON],['common_wood','Madera común',ItemType::MATERIAL,ItemRarity::COMMON],['copper_ore','Mineral de cobre',ItemType::MATERIAL,ItemRarity::COMMON],['iron_ore','Mineral de hierro',ItemType::MATERIAL,ItemRarity::UNCOMMON],['coal','Carbón',ItemType::MATERIAL,ItemRarity::COMMON],['dragon_essence_fragment','Fragmento de esencia de dragón',ItemType::DRAGON_MATERIAL,ItemRarity::RARE]
  ] as $i){$v->assertItemType($i[2]);$v->assertItemRarity($i[3]);$v->assertItemStack(true,99);$items[$i[0]]=Item::updateOrCreate(['code'=>$v->normalizeCode($i[0])],['name'=>$i[1],'description'=>'Objeto de catálogo inicial','item_type'=>$i[2],'rarity'=>$i[3],'is_stackable'=>true,'max_stack'=>99,'status'=>$active]);}
  $monsters=[]; foreach([
   ['grey_wolf','Lobo gris',1,30,6,2,'75.00','8.00','3.00',10],['young_boar','Jabalí joven',2,45,7,3,'70.00','4.00','2.00',14],['goblin_scout','Goblin explorador',3,40,9,4,'78.00','6.00','4.00',18],['cave_bat','Murciélago de cueva',4,35,10,3,'82.00','12.00','3.00',20],['goblin_miner','Goblin minero',5,60,12,6,'72.00','3.00','3.00',26]
  ] as $m){$monsters[$m[0]]=Monster::updateOrCreate(['code'=>$v->normalizeCode($m[0])],['name'=>$m[1],'description'=>'Estadísticas provisionales sin balance definitivo','level'=>$m[2],'max_health'=>$m[3],'attack'=>$m[4],'defense'=>$m[5],'accuracy_rate'=>$m[6],'evasion_rate'=>$m[7],'critical_chance'=>$m[8],'experience_reward'=>$m[9],'status'=>$active]);}
  foreach([['dawn_village','grey_oak_forest'],['grey_oak_forest','abandoned_mines']] as $c){$v->assertConnection($zones[$c[0]]->id,$zones[$c[1]]->id);ZoneConnection::updateOrCreate(['from_zone_id'=>$zones[$c[0]]->id,'to_zone_id'=>$zones[$c[1]]->id],['travel_type'=>TravelType::ROAD,'is_bidirectional'=>true,'minimum_level'=>1,'required_item_id'=>null,'status'=>$active,'sort_order'=>1]);}
  $spawns=[['grey_oak_forest','grey_wolf',50,1,5],['grey_oak_forest','young_boar',30,1,5],['grey_oak_forest','goblin_scout',20,1,5],['abandoned_mines','cave_bat',45,4,10],['abandoned_mines','goblin_miner',40,4,10],['abandoned_mines','goblin_scout',15,4,10]];
  foreach($spawns as $s){$v->assertZoneMonster($s[2],$s[3],$s[4]);ZoneMonster::updateOrCreate(['zone_id'=>$zones[$s[0]]->id,'monster_id'=>$monsters[$s[1]]->id],['weight'=>$s[2],'minimum_character_level'=>$s[3],'maximum_character_level'=>$s[4],'status'=>$active]);}
 });}
}
