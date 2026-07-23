<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
class AddVisualConfigurationToItemRarities extends Migration
{
    public function up()
    {
        Schema::table('item_rarities',function(Blueprint $table){$table->char('border_color_hex',7)->default('#A3A3A3');$table->unsignedSmallInteger('border_opacity_basis_points')->default(10000);$table->unsignedTinyInteger('border_width_px')->default(1);$table->char('inner_glow_color_hex',7)->default('#A3A3A3');$table->unsignedSmallInteger('inner_glow_opacity_basis_points')->default(0);$table->unsignedTinyInteger('inner_glow_blur_px')->default(0);$table->unsignedTinyInteger('inner_glow_spread_px')->default(0);});
        $rows=['common'=>['#A3A3A3',10000,1,'#A3A3A3',0,0,0],'rare'=>['#2563EB',10000,2,'#2563EB',2000,16,1],'mythic'=>['#7E22CE',10000,2,'#7E22CE',2800,18,1],'legendary'=>['#B7791F',10000,2,'#B7791F',3500,20,2]];
        foreach($rows as $code=>$v)DB::table('item_rarities')->where('code',$code)->update(['border_color_hex'=>$v[0],'border_opacity_basis_points'=>$v[1],'border_width_px'=>$v[2],'inner_glow_color_hex'=>$v[3],'inner_glow_opacity_basis_points'=>$v[4],'inner_glow_blur_px'=>$v[5],'inner_glow_spread_px'=>$v[6]]);
    }
    public function down(){Schema::table('item_rarities',function(Blueprint $table){$table->dropColumn(['border_color_hex','border_opacity_basis_points','border_width_px','inner_glow_color_hex','inner_glow_opacity_basis_points','inner_glow_blur_px','inner_glow_spread_px']);});}
}
