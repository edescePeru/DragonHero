<?php
namespace App\Domain\Hunts\Playback;use App\Models\Character;use App\Models\HuntingSession;use App\Models\Zone;use Carbon\CarbonImmutable;final class FixedHuntingPlaybackSpeedProvider implements HuntingPlaybackSpeedProvider{public function multiplier(Character $character,Zone $zone,CarbonImmutable $now,HuntingSession $session=null){return HuntingPlaybackLimits::BASE_SPEED_MULTIPLIER;}}
