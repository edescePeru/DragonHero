<?php

namespace Database\Seeders;

use App\Models\CharacterLevelRequirement;
use Illuminate\Database\Seeder;

class CharacterLevelRequirementSeeder extends Seeder
{
    public function run()
    {
        foreach ([1 => 0, 2 => 100, 3 => 250, 4 => 500, 5 => 850] as $level => $experience) {
            CharacterLevelRequirement::updateOrCreate(
                ['level' => $level],
                ['required_experience' => $experience]
            );
        }
    }
}
