<?php

namespace Tests\Feature;

use App\Domain\Inventory\Instances\EffectiveItemStatsResolver;
use App\Domain\Inventory\Instances\ItemInstanceRarityResolver;
use App\Domain\Inventory\Instances\RefinementStatScaling;
use App\Models\Character;
use App\Models\Item;
use App\Models\ItemInstance;
use App\Models\ItemRarity;
use App\Models\RefinementStatModifier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class ItemRarityAndRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_catalog_contains_exactly_four_rarities_and_curve()
    {
        $this->assertSame(['common','rare','mythic','legendary'], ItemRarity::orderBy('sort_order')->pluck('code')->all());
        $scaling=app(RefinementStatScaling::class);$this->assertSame(100,$scaling->basisPoints(1));$this->assertSame(1000,$scaling->basisPoints(10));$this->assertSame(2000,$scaling->basisPoints(11));$this->assertSame(5000,$scaling->basisPoints(15));
    }

    public function test_rarity_resolver_enforces_allowed_values_and_common_fallback()
    {
        $item = $this->weapon();
        $common = ItemRarity::where('code','common')->firstOrFail();
        $mythic = ItemRarity::where('code','mythic')->firstOrFail();
        $item->allowedRarities()->sync([$common->id,$mythic->id]);
        $resolver = app(ItemInstanceRarityResolver::class);
        $this->assertSame('common',$resolver->resolve($item)->code);
        $this->assertSame('mythic',$resolver->resolve($item,'mythic')->code);
        $item->allowedRarities()->sync([$mythic->id]);
        try{$resolver->resolve($item);$this->fail('Expected explicit rarity requirement.');}catch(InvalidArgumentException $exception){$this->assertStringContainsString('required',$exception->getMessage());}
        try{$resolver->resolve($item,'legendary');$this->fail('Expected disallowed rarity rejection.');}catch(InvalidArgumentException $exception){$this->assertStringContainsString('not allowed',$exception->getMessage());}
    }

    public function test_refinement_scales_only_primary_stat_and_rarity_is_not_scaled()
    {
        $item = $this->weapon(['attack_bonus'=>100,'accuracy_bonus'=>2,'critical_chance_bonus'=>'1.00','allows_refinement'=>true,'refinement_stat'=>'attack']);
        $legendary = ItemRarity::where('code','legendary')->firstOrFail();
        $item->allowedRarities()->sync([$legendary->id]);
        $instance = $this->makeItemInstance($item,$legendary,15);
        $stats = app(EffectiveItemStatsResolver::class)->resolve($item,$instance);
        $this->assertSame(150,$stats->total()->attack());
        $this->assertSame(7,$stats->total()->accuracy());
        $this->assertEquals(5.0,$stats->total()->criticalChance());
        $this->assertSame(0,$stats->refinement()->accuracy());
        $this->assertEquals(0.0,$stats->refinement()->criticalChance());
    }

    public function test_defense_rounds_once_and_legendary_special_stats_are_separate()
    {
        $item = $this->armor(['defense_bonus'=>37,'allows_refinement'=>true,'refinement_stat'=>'defense']);
        $legendary = ItemRarity::where('code','legendary')->firstOrFail();
        $item->allowedRarities()->sync([$legendary->id]);
        $stats = app(EffectiveItemStatsResolver::class)->resolve($item,$this->makeItemInstance($item,$legendary,15));
        $this->assertSame(56,$stats->total()->defense());
        $this->assertSame(4,$stats->total()->evasion());
        $this->assertEquals(2.0,$stats->total()->attackSpeed());
        $this->assertSame(100,$stats->total()->absorbDamageBasisPoints());
    }

    public function test_instance_rarity_is_immutable_and_must_be_allowed()
    {
        $item = $this->weapon();
        $common = ItemRarity::where('code','common')->firstOrFail();
        $rare = ItemRarity::where('code','rare')->firstOrFail();
        $item->allowedRarities()->sync([$common->id]);
        try{$this->makeItemInstance($item,$rare,0);$this->fail('Expected model invariant.');}catch(LogicException $exception){$this->assertStringContainsString('not allowed',$exception->getMessage());}
        $instance=$this->makeItemInstance($item,$common,0);$item->allowedRarities()->sync([$rare->id]);$this->assertSame($common->id,$instance->fresh()->item_rarity_id);
    }

    public function test_legacy_admin_payload_receives_common_and_refinement_defaults()
    {
        config(['game_admin.emails'=>['rarity-admin@example.test']]);$admin=User::factory()->create(['email'=>'rarity-admin@example.test']);
        $response=$this->actingAs($admin)->post(route('admin.content.items.store'),['code'=>'compat_weapon','name'=>'Compat','item_type'=>'equipment','equipment_type'=>'weapon','hand_requirement'=>'one_hand','equipment_family'=>'sword','is_stackable'=>0,'max_stack'=>1,'status'=>'active','max_health_bonus'=>0,'attack_bonus'=>5,'defense_bonus'=>0,'accuracy_bonus'=>0,'evasion_bonus'=>0,'critical_chance_bonus'=>0,'attack_speed_bonus'=>0]);
        $response->assertSessionHasNoErrors();$item=Item::where('code','compat_weapon')->firstOrFail();$this->assertTrue($item->allows_refinement);$this->assertSame('attack',$item->refinement_stat);$this->assertSame(['common'],$item->allowedRarities()->pluck('code')->all());
    }

    public function test_admin_updates_refinable_weapon_with_valid_absorb_damage_percentages()
    {
        config(['game_admin.emails'=>['rarity-admin@example.test']]);
        $admin=User::factory()->create(['email'=>'rarity-admin@example.test']);
        $item=$this->weapon(['code'=>'absorb_weapon_valid']);
        $common=ItemRarity::where('code','common')->firstOrFail();
        $rare=ItemRarity::where('code','rare')->firstOrFail();
        $item->allowedRarities()->sync([$common->id]);
        $values=['0.00'=>0,'10'=>1000,'10.00'=>1000,'0.01'=>1,'9.99'=>999];

        foreach($values as $percent=>$basisPoints){
            $response=$this->actingAs($admin)->put(
                route('admin.content.items.update',$item),
                $this->adminWeaponPayload($item,[$common->id,$rare->id],$percent)
            );

            $response->assertRedirect()->assertSessionHasNoErrors();
            $item->refresh();
            $this->assertSame($basisPoints,(int)$item->absorb_damage_basis_points);
            $this->assertTrue((bool)$item->allows_refinement);
            $this->assertSame('attack',$item->refinement_stat);
            $this->assertEqualsCanonicalizing(
                [$common->id,$rare->id],
                $item->allowedRarities()->pluck('item_rarities.id')->all()
            );
        }
    }

    /**
     * @dataProvider invalidAbsorbDamagePercentages
     */
    public function test_admin_rejects_invalid_absorb_damage_percentages_without_php_errors($percent,$errorField)
    {
        config(['game_admin.emails'=>['rarity-admin@example.test']]);
        $admin=User::factory()->create(['email'=>'rarity-admin@example.test']);
        $item=$this->weapon(['code'=>'absorb_weapon_invalid']);
        $common=ItemRarity::where('code','common')->firstOrFail();
        $item->allowedRarities()->sync([$common->id]);

        $response=$this->actingAs($admin)->from(route('admin.content.items.edit',$item))->put(
            route('admin.content.items.update',$item),
            $this->adminWeaponPayload($item,[$common->id],$percent)
        );

        $response->assertRedirect(route('admin.content.items.edit',$item))
            ->assertSessionHasErrors($errorField);
    }

    public function invalidAbsorbDamagePercentages()
    {
        return [
            ['10.01','absorb_damage_basis_points'],
            ['11','absorb_damage_percent'],
            ['-1','absorb_damage_percent'],
            ['1.000','absorb_damage_percent'],
            ['01','absorb_damage_percent'],
            ['not-numeric','absorb_damage_percent'],
        ];
    }

    public function test_admin_can_only_edit_an_official_rarity()
    {
        config(['game_admin.emails'=>['rarity-admin@example.test']]);$admin=User::factory()->create(['email'=>'rarity-admin@example.test']);$normal=User::factory()->create();
        $this->actingAs($normal)->get(route('admin.content.item-rarities.index'))->assertForbidden();
        $rare=ItemRarity::where('code','rare')->firstOrFail();$payload=['name'=>'Raro ajustado','status'=>'active','sort_order'=>22,'visual_style'=>'blue','weapon_accuracy_bonus_basis_points'=>450,'weapon_critical_bonus_basis_points'=>0,'armor_evasion_bonus_basis_points'=>180,'armor_speed_bonus_hundredths'=>0,'armor_absorb_damage_bonus_basis_points'=>0,'border_color_hex'=>'#2563eb','border_opacity_percent'=>'100','border_width_px'=>2,'inner_glow_color_hex'=>'#2563eb','inner_glow_opacity_percent'=>'20','inner_glow_blur_px'=>16,'inner_glow_spread_px'=>1];
        $this->actingAs($admin)->get(route('admin.content.item-rarities.index'))->assertOk()->assertSee('common')->assertSee('legendary');
        $this->put(route('admin.content.item-rarities.update',$rare),$payload)->assertSessionHasNoErrors();$this->assertSame('Raro ajustado',$rare->fresh()->name);$this->assertSame(4,ItemRarity::count());
        $this->post('/admin/content/item-rarities',$payload)->assertStatus(405);
    }

    private function weapon(array $overrides=[])
    {
        return Item::create(array_merge(['code'=>'weapon-'.uniqid(),'name'=>'Weapon','item_type'=>'equipment','equipment_type'=>'weapon','equipment_family'=>'sword','hand_requirement'=>'one_hand','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'status'=>'active','required_level'=>1,'max_health_bonus'=>0,'attack_bonus'=>10,'defense_bonus'=>0,'accuracy_bonus'=>0,'evasion_bonus'=>0,'critical_chance_bonus'=>'0.00','attack_speed_bonus'=>'0.00','absorb_damage_basis_points'=>0,'allows_refinement'=>true,'refinement_stat'=>'attack'], $overrides));
    }

    private function armor(array $overrides=[])
    {
        return Item::create(array_merge(['code'=>'armor-'.uniqid(),'name'=>'Armor','item_type'=>'equipment','equipment_type'=>'armor','rarity'=>'common','is_stackable'=>false,'max_stack'=>1,'status'=>'active','required_level'=>1,'max_health_bonus'=>0,'attack_bonus'=>0,'defense_bonus'=>10,'accuracy_bonus'=>0,'evasion_bonus'=>0,'critical_chance_bonus'=>'0.00','attack_speed_bonus'=>'0.00','absorb_damage_basis_points'=>0,'allows_refinement'=>true,'refinement_stat'=>'defense'], $overrides));
    }

    private function adminWeaponPayload(Item $item,array $rarityIds,$absorbDamagePercent)
    {
        return [
            'code'=>$item->code,
            'name'=>'Updated weapon',
            'description'=>'Regression fixture',
            'item_type'=>'equipment',
            'equipment_type'=>'weapon',
            'hand_requirement'=>'one_hand',
            'equipment_family'=>'sword',
            'required_level'=>1,
            'character_class_ids'=>[],
            'allowed_rarity_ids'=>$rarityIds,
            'allows_refinement'=>1,
            'refinement_stat'=>'attack',
            'is_stackable'=>0,
            'max_stack'=>1,
            'is_sellable'=>0,
            'sell_price'=>0,
            'status'=>'active',
            'max_health_bonus'=>0,
            'attack_bonus'=>10,
            'defense_bonus'=>0,
            'accuracy_bonus'=>0,
            'evasion_bonus'=>0,
            'critical_chance_bonus'=>'0.00',
            'attack_speed_bonus'=>'0.00',
            'absorb_damage_percent'=>$absorbDamagePercent,
        ];
    }

    private function makeItemInstance(Item $item,ItemRarity $rarity,$level)
    {
        $character=Character::factory()->for(User::factory())->create();
        return ItemInstance::create(['uuid'=>$this->uuid(),'character_id'=>$character->id,'item_id'=>$item->id,'item_rarity_id'=>$rarity->id,'refinement_level'=>$level,'status'=>'available','origin_type'=>'legacy_inventory','origin_id'=>random_int(1,PHP_INT_MAX),'origin_unit_index'=>1,'acquired_at'=>now()]);
    }

    private function uuid(){return sprintf('%08x-0000-5000-8000-%012x',random_int(1,0x7fffffff),random_int(1,0x7fffffff));}
}
