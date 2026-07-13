<?php
namespace App\Domain\Hunts\Playback;use App\Models\Character;use App\Models\HuntingSession;use App\Models\Zone;use Carbon\CarbonImmutable;interface HuntingPlaybackSpeedProvider{public function multiplier(Character $character,Zone $zone,CarbonImmutable $now,HuntingSession $session=null);}
