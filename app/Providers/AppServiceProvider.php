<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(\App\Domain\Random\RandomNumberGenerator::class, \App\Domain\Random\NativeRandomNumberGenerator::class);
        $this->app->bind(\App\Domain\Hunts\Playback\HuntingPlaybackSpeedProvider::class, \App\Domain\Hunts\Playback\FixedHuntingPlaybackSpeedProvider::class);
        $this->app->singleton(\App\Domain\Combat\CombatMitigationConfigProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Relation::enforceMorphMap(['game_home_card'=>\App\Models\GameHomeCard::class,'character'=>\App\Models\Character::class,'character_class'=>\App\Models\CharacterClass::class,'character_template'=>\App\Models\CharacterTemplate::class,'monster'=>\App\Models\Monster::class,'item'=>\App\Models\Item::class,'zone'=>\App\Models\Zone::class,'region'=>\App\Models\Region::class,'world'=>\App\Models\World::class,'npc'=>\App\Models\Npc::class,'shop'=>\App\Models\Shop::class]);
    }
}
