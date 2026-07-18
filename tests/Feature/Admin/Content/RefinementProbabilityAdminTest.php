<?php

namespace Tests\Feature\Admin\Content;

use App\Models\Item;
use App\Models\RefinementLevel;
use App\Models\RefinementLevelMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefinementProbabilityAdminTest extends TestCase
{
    use RefreshDatabase;
    private $admin;
    protected function setUp(): void { parent::setUp(); config(['game_admin.emails'=>['refinement-admin@example.test']]); $this->admin=User::factory()->create(['email'=>'refinement-admin@example.test']); }

    public function test_admin_accepts_real_probability_and_shows_percentage_and_failure_help()
    {
        $this->actingAs($this->admin)->post(route('admin.content.refinement.store'),['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>8000,'gold_cost'=>10,'failure_behavior'=>'keep_level','status'=>'active'])->assertSessionHas('status','Regla creada.');
        $this->assertDatabaseHas('refinement_levels',['success_chance_basis_points'=>8000,'failure_behavior'=>'keep_level']);
        $this->get(route('admin.content.refinement.index'))->assertOk()->assertSee('8000 BP (80 %)')->assertSee('En caso de fallo se consumen el oro y los materiales, pero el objeto conserva su nivel y no se destruye.');
    }

    public function test_admin_rejects_probability_outside_range_and_other_failure_behavior()
    {
        $this->actingAs($this->admin);
        foreach([0,10001] as $chance)$this->post(route('admin.content.refinement.store'),['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>$chance,'gold_cost'=>0,'failure_behavior'=>'keep_level','status'=>'active'])->assertSessionHasErrors('success_chance_basis_points');
        $this->post(route('admin.content.refinement.store'),['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>5000,'gold_cost'=>0,'failure_behavior'=>'downgrade','status'=>'active'])->assertSessionHasErrors('failure_behavior');
    }

    public function test_explicit_activation_revalidates_materials_and_deactivation_only_changes_status()
    {
        $item=Item::create(['code'=>'admin_refinement_material','name'=>'Material','item_type'=>'material','rarity'=>'common','is_stackable'=>true,'max_stack'=>99,'status'=>'active']);
        $rule=RefinementLevel::create(['from_level'=>0,'to_level'=>1,'success_chance_basis_points'=>5000,'gold_cost'=>10,'failure_behavior'=>'keep_level','status'=>'inactive']);
        RefinementLevelMaterial::create(['refinement_level_id'=>$rule->id,'item_id'=>$item->id,'quantity'=>1]);
        $this->actingAs($this->admin)->patch(route('admin.content.refinement.activate',$rule))->assertSessionHas('status','Regla activada.');
        $this->assertSame('active',$rule->fresh()->status);
        $this->patch(route('admin.content.refinement.deactivate',$rule))->assertSessionHas('status','Regla desactivada.');
        $this->assertSame('inactive',$rule->fresh()->status);$this->assertSame(5000,$rule->fresh()->success_chance_basis_points);$this->assertDatabaseHas('refinement_level_materials',['refinement_level_id'=>$rule->id,'item_id'=>$item->id,'quantity'=>1]);
    }
}
