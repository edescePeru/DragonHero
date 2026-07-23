<?php
namespace Tests\Feature;
use App\Domain\Inventory\Instances\Rarity\ItemRarityVisualStyleResolver;
use App\Domain\Probability\PercentageBasisPointsConverter;
use App\Models\ItemRarity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;
final class ItemRarityVisualTest extends TestCase
{
    use RefreshDatabase;
    public function test_schema_and_official_visual_defaults_are_available()
    {
        foreach(['border_color_hex','border_opacity_basis_points','border_width_px','inner_glow_color_hex','inner_glow_opacity_basis_points','inner_glow_blur_px','inner_glow_spread_px']as$column)$this->assertTrue(Schema::hasColumn('item_rarities',$column));
        $this->assertSame(['#A3A3A3','#2563EB','#7E22CE','#B7791F'],ItemRarity::orderBy('sort_order')->pluck('border_color_hex')->all());
        $this->assertSame([0,2000,2800,3500],ItemRarity::orderBy('sort_order')->pluck('inner_glow_opacity_basis_points')->all());
    }
    public function test_down_removes_only_visual_columns_and_up_restores_them()
    {
        $codes=ItemRarity::orderBy('id')->pluck('code')->all();$accuracy=ItemRarity::orderBy('id')->pluck('weapon_accuracy_bonus_basis_points')->all();$instances=\DB::table('item_instances')->count();$migration=new \AddVisualConfigurationToItemRarities();$migration->down();$this->assertFalse(Schema::hasColumn('item_rarities','border_color_hex'));$this->assertTrue(Schema::hasColumn('item_rarities','visual_style'));$this->assertSame($codes,ItemRarity::orderBy('id')->pluck('code')->all());$this->assertSame($accuracy,ItemRarity::orderBy('id')->pluck('weapon_accuracy_bonus_basis_points')->all());$this->assertSame($instances,\DB::table('item_instances')->count());$migration->up();$this->assertTrue(Schema::hasColumn('item_rarities','border_color_hex'));
    }
    public function test_converter_is_exact_and_allows_zero()
    {
        $c=app(PercentageBasisPointsConverter::class);$this->assertSame(0,$c->toBasisPoints('0'));$this->assertSame(2500,$c->toBasisPoints('25'));$this->assertSame(3550,$c->toBasisPoints('35.50'));$this->assertSame(10000,$c->toBasisPoints('100.00'));$this->assertSame('0',$c->toCssOpacity(0));$this->assertSame('0.2',$c->toCssOpacity(2000));$this->assertSame('0.35',$c->toCssOpacity(3500));$this->assertSame('1',$c->toCssOpacity(10000));
    }
    public function test_resolver_returns_only_allowed_variables_and_falls_back_without_querying()
    {
        $rarity=ItemRarity::where('code','legendary')->firstOrFail();$resolver=app(ItemRarityVisualStyleResolver::class);$visual=$resolver->resolve($rarity);$this->assertSame('#B7791F',$visual->borderColorHex());$this->assertSame('183, 121, 31',$visual->borderRgb());$this->assertSame(['--rarity-border-rgb','--rarity-border-opacity','--rarity-border-width','--rarity-glow-rgb','--rarity-glow-opacity','--rarity-glow-blur','--rarity-glow-spread'],array_keys($visual->cssVariables()));
        $rarity->border_color_hex='url(x)';$this->assertSame('#B7791F',$resolver->resolve($rarity)->borderColorHex());
    }
    public function test_admin_form_and_update_are_safe_and_dynamic()
    {
        config(['game_admin.emails'=>['visual-admin@example.test']]);$admin=User::factory()->create(['email'=>'visual-admin@example.test']);$rare=ItemRarity::where('code','rare')->firstOrFail();$this->actingAs($admin)->get(route('admin.content.item-rarities.index'))->assertOk()->assertSee('type="color"',false)->assertSee('data-rarity-preview',false)->assertSee('Usar color del borde');
        $payload=['name'=>$rare->name,'status'=>'active','sort_order'=>20,'visual_style'=>'blue','weapon_accuracy_bonus_basis_points'=>500,'weapon_critical_bonus_basis_points'=>0,'armor_evasion_bonus_basis_points'=>200,'armor_speed_bonus_hundredths'=>0,'armor_absorb_damage_bonus_basis_points'=>0,'border_color_hex'=>'#abcdef','border_opacity_percent'=>'35.50','border_width_px'=>5,'inner_glow_color_hex'=>'#123456','inner_glow_opacity_percent'=>'0','inner_glow_blur_px'=>40,'inner_glow_spread_px'=>20];
        $this->put(route('admin.content.item-rarities.update',$rare),$payload)->assertSessionHasNoErrors();$rare->refresh();$this->assertSame('#ABCDEF',$rare->border_color_hex);$this->assertSame(3550,$rare->border_opacity_basis_points);
        foreach(['#FFF','red','rgb(1,2,3)','url(x)','var(--x)','calc(1)',';color:red','!important']as$invalid){$payload['border_color_hex']=$invalid;$this->put(route('admin.content.item-rarities.update',$rare),$payload)->assertSessionHasErrors('border_color_hex');}
    }
    public function test_invalid_visual_ranges_are_rejected_by_domain()
    {
        $rarity=ItemRarity::where('code','common')->firstOrFail();$rarity->border_width_px=6;$rarity->visual_style='neutral';$this->assertSame(1,app(ItemRarityVisualStyleResolver::class)->resolve($rarity)->borderWidthPx());
        $this->expectException(InvalidArgumentException::class);app(PercentageBasisPointsConverter::class)->toBasisPoints('100.01');
    }
}
