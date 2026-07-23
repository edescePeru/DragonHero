<?php

namespace Tests\Feature;

use App\Domain\Inventory\ItemClassification;
use App\Domain\Loot\Data\LootDrop;
use App\Domain\Loot\LootGenerator;
use App\Domain\Random\RandomNumberGenerator;
use App\Models\Monster;
use Database\Seeders\WorldCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LootSequenceRandom implements RandomNumberGenerator
{
    public $values;
    public $ranges = [];

    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function randomInt(int $min, int $max): int
    {
        $this->ranges[] = [$min, $max];

        return array_shift($this->values);
    }
}

class LootGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(WorldCatalogSeeder::class);
    }

    private function generator(LootSequenceRandom $rng)
    {
        return new LootGenerator($rng, app(ItemClassification::class));
    }

    public function test_none_one_multiple_and_variable_drops()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $none = $this->generator(new LootSequenceRandom([700001, 250001]))->generateFor($wolf);
        $this->assertCount(0, $none->drops());

        $one = $this->generator(new LootSequenceRandom([700000, 2, 250001]))->generateFor($wolf);
        $this->assertCount(1, $one->drops());
        $this->assertSame(2, $one->drops()[0]->quantity());

        $many = $this->generator(new LootSequenceRandom([1, 3, 1]))->generateFor($wolf);
        $this->assertCount(2, $many->drops());
        $this->assertContainsOnlyInstancesOf(LootDrop::class, $many->drops());
    }

    public function test_probability_roll_uses_ppm_bounds_and_exact_boundary()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $rng = new LootSequenceRandom([700000, 1, 250001]);
        $result = $this->generator($rng)->generateFor($wolf);

        $this->assertCount(1, $result->drops());
        $this->assertSame(700000, $result->drops()[0]->configuredProbabilityPpm());
        $this->assertSame(700000, $result->drops()[0]->probabilityRollPpm());
        $this->assertSame([1, 1000000], $rng->ranges[0]);
        $this->assertSame([1, 1000000], $rng->ranges[2]);
    }

    public function test_fixed_quantity_does_not_consume_extra_roll()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $rng = new LootSequenceRandom([700001, 250000]);
        $result = $this->generator($rng)->generateFor($wolf);
        $this->assertCount(2, $rng->ranges);
        $this->assertSame('wolf_fang', $result->drops()[0]->itemCode());
    }

    public function test_zero_ppm_never_drops_and_one_ppm_drops_only_on_roll_one()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $entries = $wolf->lootEntries()->orderBy('id')->get();
        $entries[0]->update(['drop_probability_ppm' => 0]);
        $entries[1]->update(['drop_probability_ppm' => 1]);

        $miss = $this->generator(new LootSequenceRandom([1, 2]))->generateFor($wolf);
        $this->assertCount(0, $miss->drops());

        $hit = $this->generator(new LootSequenceRandom([1, 1]))->generateFor($wolf);
        $this->assertCount(1, $hit->drops());
        $this->assertSame(1, $hit->drops()[0]->configuredProbabilityPpm());
    }

    public function test_exact_ppm_boundaries_are_inclusive()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $entries = $wolf->lootEntries()->orderBy('id')->get();

        foreach ([[1, 1, true], [1, 2, false], [50, 50, true], [50, 51, false], [10000, 10000, true], [10000, 10001, false], [1000000, 1000000, true]] as $case) {
            list($ppm, $roll, $expected) = $case;
            $entries[0]->update(['drop_probability_ppm' => $ppm]);
            $entries[1]->update(['status' => 'inactive']);
            $result = $this->generator(new LootSequenceRandom([$roll, 1]))->generateFor($wolf);
            $this->assertSame($expected, count($result->drops()) === 1);
        }
    }

    public function test_inactive_monster_is_rejected()
    {
        $wolf = Monster::where('code', 'grey_wolf')->firstOrFail();
        $wolf->update(['status' => 'inactive']);
        $this->expectException(\InvalidArgumentException::class);
        $this->generator(new LootSequenceRandom([]))->generateFor($wolf);
    }
}
