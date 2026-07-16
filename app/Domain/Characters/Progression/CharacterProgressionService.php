<?php

namespace App\Domain\Characters\Progression;

use App\Domain\Characters\Progression\Data\CharacterExperienceProgress;
use App\Domain\Characters\Progression\Data\CharacterProgressionResult;
use App\Models\Character;
use App\Models\CharacterLevelRequirement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class CharacterProgressionService
{
    public function experienceProgress(Character $character): CharacterExperienceProgress
    {
        if (!$character->exists) throw new InvalidArgumentException('Persisted Character required.');

        $level = (int) $character->level;
        $experience = (int) $character->experience;
        $requirements = CharacterLevelRequirement::orderBy('level')->get();
        $this->validateCatalog($requirements);

        $current = $requirements->firstWhere('level', $level);
        if (!$current) throw new RuntimeException('Character level does not exist in the progression catalog.');

        $currentExperience = (int) $current->required_experience;
        if ($experience < $currentExperience) {
            throw new RuntimeException('Character experience is below the current level requirement.');
        }

        $next = $requirements->firstWhere('level', $level + 1);
        $experienceInLevel = $experience - $currentExperience;
        if (!$next) {
            return new CharacterExperienceProgress(
                $level, $experience, $currentExperience, null, null,
                $experienceInLevel, null, null, 100.0, true
            );
        }

        $nextExperience = (int) $next->required_experience;
        $requiredInLevel = $nextExperience - $currentExperience;
        $remaining = max(0, $nextExperience - $experience);
        $percentage = round(min(100, max(0, ($experienceInLevel / $requiredInLevel) * 100)), 2);

        return new CharacterExperienceProgress(
            $level, $experience, $currentExperience, (int) $next->level, $nextExperience,
            $experienceInLevel, $requiredInLevel, $remaining, $percentage, false
        );
    }

    public function grantExperienceLocked(Character $character, int $amount): CharacterProgressionResult
    {
        if (DB::transactionLevel() < 1) throw new RuntimeException('Active transaction required.');
        if (!$character->exists) throw new InvalidArgumentException('Persisted Character required.');
        if ($amount < 0) throw new InvalidArgumentException('Experience grant cannot be negative.');
        $beforeExperience = (int) $character->experience;
        $beforeLevel = (int) $character->level;
        if ($beforeExperience < 0 || $beforeLevel < 1) throw new RuntimeException('Invalid Character progression state.');
        if ($amount > PHP_INT_MAX - $beforeExperience) throw new InvalidArgumentException('Experience would exceed PHP_INT_MAX.');
        $afterExperience = $beforeExperience + $amount;
        $requirements = CharacterLevelRequirement::orderBy('level')->get();
        $this->validateCatalog($requirements);
        $eligible = $requirements->filter(function ($requirement) use ($afterExperience) {
            return (int) $requirement->required_experience <= $afterExperience;
        })->last();
        $catalogLevel = $eligible ? (int) $eligible->level : 1;
        $afterLevel = max($beforeLevel, $catalogLevel);
        if ($afterExperience < $beforeExperience || $afterLevel < $beforeLevel) throw new RuntimeException('Progression cannot decrease.');
        $character->experience = $afterExperience;
        $character->level = $afterLevel;
        $character->save();
        return new CharacterProgressionResult($beforeExperience, $afterExperience, $beforeLevel, $afterLevel);
    }

    private function validateCatalog($requirements)
    {
        if ($requirements->isEmpty()) throw new RuntimeException('Character level catalog is empty.');
        $previousLevel = 0;
        $previousExperience = -1;
        foreach ($requirements as $requirement) {
            $level = (int) $requirement->level;
            $experience = (int) $requirement->required_experience;
            if ($level !== $previousLevel + 1 || $experience < 0 || $experience <= $previousExperience) {
                throw new RuntimeException('Invalid Character level catalog.');
            }
            $previousLevel = $level;
            $previousExperience = $experience;
        }
        if ((int) $requirements->first()->level !== 1 || (int) $requirements->first()->required_experience !== 0) {
            throw new RuntimeException('Character level catalog must start at level 1 with zero experience.');
        }
    }
}
