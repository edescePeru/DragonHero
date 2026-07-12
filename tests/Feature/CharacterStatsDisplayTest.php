<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CharacterStatsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_sheet_displays_effective_stats_and_power()
    {
        $user = User::factory()->create();
        $character = Character::factory()->for($user)->create();

        $this->actingAs($user)->get(route('characters.show', $character))
            ->assertOk()
            ->assertSee('Estadísticas efectivas')
            ->assertSee('Reducción de daño')
            ->assertSee('Poder total')
            ->assertSee('147');
    }

    public function test_character_sheet_separates_stored_base_stats()
    {
        $user = User::factory()->create();
        $character = Character::factory()->for($user)->create();

        $this->actingAs($user)->get(route('characters.show', $character))
            ->assertSee('Estadísticas base almacenadas')
            ->assertSee('Vida máxima base')
            ->assertSee('Ataque base')
            ->assertSee('Crítico base');
    }

    public function test_user_still_cannot_view_another_users_character()
    {
        $owner = User::factory()->create();
        $character = Character::factory()->for($owner)->create();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get(route('characters.show', $character))->assertForbidden();
    }

    public function test_calculated_stats_are_not_persisted_as_columns()
    {
        $columns = Schema::getColumnListing('characters');

        foreach (['power', 'damage_reduction_rate', 'critical_damage_multiplier', 'attack_speed', 'loot_bonus', 'experience_bonus', 'gold_bonus'] as $column) {
            $this->assertNotContains($column, $columns);
        }
    }

    public function test_dashboard_works_with_calculated_character_stats()
    {
        $user = User::factory()->create();
        Character::factory()->for($user)->create();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertViewHas('stats')
            ->assertSee('Poder actual: 147');
    }
}
