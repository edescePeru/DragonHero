<?php

namespace App\Domain\Characters\Progression\Data;

use InvalidArgumentException;

final class CharacterExperienceProgress
{
    private $level;
    private $experience;
    private $currentLevelExperience;
    private $nextLevel;
    private $nextLevelExperience;
    private $experienceInLevel;
    private $experienceRequiredInLevel;
    private $experienceRemaining;
    private $percentage;
    private $maximumLevel;

    public function __construct(
        int $level,
        int $experience,
        int $currentLevelExperience,
        ?int $nextLevel,
        ?int $nextLevelExperience,
        int $experienceInLevel,
        ?int $experienceRequiredInLevel,
        ?int $experienceRemaining,
        float $percentage,
        bool $maximumLevel
    ) {
        if ($level < 1 || $experience < 0 || $currentLevelExperience < 0 || $experienceInLevel < 0
            || $percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Invalid Character experience progress.');
        }
        if ($maximumLevel && ($nextLevel !== null || $nextLevelExperience !== null
            || $experienceRequiredInLevel !== null || $experienceRemaining !== null || $percentage !== 100.0)) {
            throw new InvalidArgumentException('Maximum level progress cannot have a next level.');
        }
        if (!$maximumLevel && ($nextLevel === null || $nextLevelExperience === null
            || $experienceRequiredInLevel === null || $experienceRemaining === null)) {
            throw new InvalidArgumentException('Intermediate progress requires a next level.');
        }

        $this->level = $level;
        $this->experience = $experience;
        $this->currentLevelExperience = $currentLevelExperience;
        $this->nextLevel = $nextLevel;
        $this->nextLevelExperience = $nextLevelExperience;
        $this->experienceInLevel = $experienceInLevel;
        $this->experienceRequiredInLevel = $experienceRequiredInLevel;
        $this->experienceRemaining = $experienceRemaining;
        $this->percentage = $percentage;
        $this->maximumLevel = $maximumLevel;
    }

    public function level(): int { return $this->level; }
    public function experience(): int { return $this->experience; }
    public function currentLevelExperience(): int { return $this->currentLevelExperience; }
    public function nextLevel(): ?int { return $this->nextLevel; }
    public function nextLevelExperience(): ?int { return $this->nextLevelExperience; }
    public function experienceInLevel(): int { return $this->experienceInLevel; }
    public function experienceRequiredInLevel(): ?int { return $this->experienceRequiredInLevel; }
    public function experienceRemaining(): ?int { return $this->experienceRemaining; }
    public function percentage(): float { return $this->percentage; }
    public function isMaximumLevel(): bool { return $this->maximumLevel; }
}
