<?php
namespace Tests\Feature;
use App\Domain\Inventory\Instances\ItemRarityCode;use App\Domain\Inventory\Instances\Rarity\ItemRarityDropConfigurationService;use App\Domain\Inventory\Instances\Rarity\ItemRarityRoller;use App\Domain\Random\RandomNumberGenerator;use App\Models\Item;use App\Models\ItemRarity;use App\Models\ItemRarityDropSetting;use App\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Tests\TestCase;
final class ItemRarityDropTest extends TestCase
{
    use RefreshDatabase;
    public function test_global_boundaries_and_downward_mapping_are_integer_and_deterministic()
    {
        $item=Item::create(['code'=>'rarity-sword','name'=>'Rarity sword','description'=>'','item_type'=>'equipment','equipment_type'=>'weapon','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'status'=>'active']);
        $rarities=ItemRarity::whereIn('code',[ItemRarityCode::COMMON,ItemRarityCode::LEGENDARY])->get();$item->allowedRarities()->sync($rarities->pluck('id'));$item->load('allowedRarities');
        $rareRoll=new FixedRarityRandom(970000);$result=(new ItemRarityRoller($rareRoll,app(ItemRarityDropConfigurationService::class)))->roll($item);
        $this->assertSame(ItemRarityCode::RARE,$result->rolledRarityCode());$this->assertSame(ItemRarityCode::COMMON,$result->resolvedRarityCode());$this->assertSame('nearest_allowed_lower',$result->mappingReason());$this->assertSame(1,$rareRoll->calls);
        $legendary=(new ItemRarityRoller(new FixedRarityRandom(999951),app(ItemRarityDropConfigurationService::class)))->roll($item);$this->assertSame(ItemRarityCode::LEGENDARY,$legendary->resolvedRarityCode());
    }
    public function test_single_allowed_rarity_does_not_consume_rng_or_configuration()
    {
        ItemRarityDropSetting::whereKey(1)->delete();$item=Item::create(['code'=>'fixed-sword','name'=>'Fixed sword','description'=>'','item_type'=>'equipment','equipment_type'=>'weapon','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'status'=>'active']);$legendary=ItemRarity::where('code','legendary')->firstOrFail();$item->allowedRarities()->sync([$legendary->id]);$item->load('allowedRarities');$rng=new FixedRarityRandom(1);$result=(new ItemRarityRoller($rng,app(ItemRarityDropConfigurationService::class)))->roll($item);$this->assertSame('legendary',$result->resolvedRarityCode());$this->assertTrue($result->usedFixedRarity());$this->assertSame(0,$rng->calls);
    }
    public function test_admin_access_exact_total_and_optimistic_version()
    {
        config(['game_admin.emails'=>['rarity-admin@example.test']]);$admin=User::factory()->create(['email'=>'rarity-admin@example.test']);
        $this->actingAs(User::factory()->create())->get(route('admin.content.item-rarity-drop-rates.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.content.item-rarity-drop-rates.index'))->assertOk()->assertSee('949000 PPM');
        $data=['version'=>1,'common_probability_percent'=>'94.9000','rare_probability_percent'=>'4.9000','mythic_probability_percent'=>'0.1950','legendary_probability_percent'=>'0.0050'];
        $this->put(route('admin.content.item-rarity-drop-rates.update'),$data)->assertSessionHasNoErrors();$this->assertSame(2,ItemRarityDropSetting::findOrFail(1)->version);
        $data['version']=1;$this->put(route('admin.content.item-rarity-drop-rates.update'),$data)->assertSessionHasErrors('version');
        $data['version']=2;$data['common_probability_percent']='90';$this->put(route('admin.content.item-rarity-drop-rates.update'),$data)->assertSessionHasErrors('total');
    }
}
final class FixedRarityRandom implements RandomNumberGenerator{public $calls=0;private $value;public function __construct(int $value){$this->value=$value;}public function randomInt(int $minimum,int $maximum):int{$this->calls++;return$this->value;}}
