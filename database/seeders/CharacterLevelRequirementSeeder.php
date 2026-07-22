<?php

namespace Database\Seeders;

use App\Models\CharacterLevelRequirement;
use Illuminate\Support\Facades\DB;
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
        DB::table('character_progression_settings')->where('id',1)->where('version',1)->whereNull('updated_by')->where('max_character_level','<',5)->update(['max_character_level'=>5,'updated_at'=>now()]);
    }
}
