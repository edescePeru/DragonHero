<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Auth\ShowRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\WorldCatalogController;
use App\Http\Controllers\RegionCatalogController;
use App\Http\Controllers\ZoneCatalogController;
use App\Http\Controllers\WorldMapController;
use App\Http\Controllers\CharacterInventoryController;
use App\Http\Controllers\CharacterWalletController;
use App\Http\Controllers\CharacterHuntController;
use App\Http\Controllers\CharacterHuntingSessionController;
use App\Http\Controllers\CharacterHuntRewardClaimController;
use App\Http\Controllers\CharacterEquipmentController;
use App\Http\Controllers\CharacterItemRefinementController;
use App\Http\Controllers\CharacterOverviewController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\Content\ItemController as AdminItemController;
use App\Http\Controllers\Admin\Content\MonsterController as AdminMonsterController;
use App\Http\Controllers\Admin\Content\ZoneController as AdminZoneController;
use App\Http\Controllers\Admin\Content\ZoneMonsterController as AdminZoneMonsterController;
use App\Http\Controllers\Admin\Content\LootController as AdminLootController;
use App\Http\Controllers\Admin\Content\RefinementController as AdminRefinementController;
use App\Http\Controllers\Admin\Content\RefinementStatModifierController as AdminRefinementStatModifierController;
use App\Http\Controllers\Admin\Content\WorldMapController as AdminWorldMapController;
use App\Http\Controllers\Admin\Content\WorldMapAreaController as AdminWorldMapAreaController;

Route::middleware('guest')->group(function () {
    Route::get('/login', ShowLoginController::class)->name('login');
    Route::post('/login', LoginController::class);
    Route::get('/registro', ShowRegistrationController::class)->name('register');
    Route::post('/registro', RegisterController::class);
});

Route::middleware(['auth', 'game.navigation'])->group(function () {
    Route::get('/characters/create', [CharacterController::class, 'create'])->name('characters.create');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::get('/characters/{character}/overview', CharacterOverviewController::class)->name('characters.overview');
    Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');
    Route::get('/characters/{character}/inventory', [CharacterInventoryController::class, 'index'])->name('characters.inventory.index');
    Route::get('/characters/{character}/wallet', [CharacterWalletController::class, 'show'])->name('characters.wallet.show');
    Route::get('/characters/{character}/hunts', [CharacterHuntController::class, 'index'])->name('characters.hunts.index');
    Route::get('/characters/{character}/hunts/{hunt}', [CharacterHuntController::class, 'show'])->name('characters.hunts.show');
    Route::post('/characters/{character}/zones/{zone}/hunts', [CharacterHuntController::class, 'store'])->name('characters.hunts.store');
    Route::post('/characters/{character}/zones/{zone}/hunting-sessions', [CharacterHuntingSessionController::class, 'store'])->name('characters.hunting-sessions.store');
    Route::get('/characters/{character}/hunting-sessions/{huntingSession}', [CharacterHuntingSessionController::class, 'show'])->name('characters.hunting-sessions.show');
    Route::post('/characters/{character}/hunting-sessions/{huntingSession}/tick', [CharacterHuntingSessionController::class, 'tick'])->name('characters.hunting-sessions.tick');
    Route::post('/characters/{character}/hunting-sessions/{huntingSession}/stop', [CharacterHuntingSessionController::class, 'stop'])->name('characters.hunting-sessions.stop');
    Route::post('/characters/{character}/hunt-rewards/claim', CharacterHuntRewardClaimController::class)->name('characters.hunt-rewards.claim');
    Route::post('/characters/{character}/equipment/equip', [CharacterEquipmentController::class, 'equip'])->name('characters.equipment.equip');
    Route::post('/characters/{character}/equipment/unequip', [CharacterEquipmentController::class, 'unequip'])->name('characters.equipment.unequip');
    Route::post('/characters/{character}/item-instances/{itemInstance}/refine', CharacterItemRefinementController::class)->name('characters.item-instances.refine');

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('character.required')
        ->name('dashboard');
    Route::get('/inventory.html', [DashboardController::class, 'inventory']);
    Route::get('/reports.html', [DashboardController::class, 'reports']);
    Route::get('/create-product.html', [DashboardController::class, 'createProduct']);
    Route::get('/docs.html', [DashboardController::class, 'docs']);
    Route::post('/logout', LogoutController::class)->name('logout');
});

Route::middleware(['auth', 'game.navigation', 'character.required'])->group(function () {
    Route::get('/maps',[WorldMapController::class,'index'])->name('world-maps.index');
    Route::get('/maps/worlds/{world}',[WorldMapController::class,'world'])->name('world-maps.world');
    Route::get('/maps/regions/{region}',[WorldMapController::class,'region'])->name('world-maps.region');
    Route::get('/maps/{worldMap}',[WorldMapController::class,'show'])->name('world-maps.show');
    Route::get('/worlds', [WorldCatalogController::class, 'index'])->name('worlds.index');
    Route::get('/worlds/{world}', [WorldCatalogController::class, 'show'])->name('worlds.show');
    Route::get('/regions/{region}', [RegionCatalogController::class, 'show'])->name('regions.show');
    Route::get('/zones/{zone}', [ZoneCatalogController::class, 'show'])->name('zones.show');
});

Route::prefix('admin/content')->name('admin.content.')->middleware(['auth','game.navigation','content.admin'])->group(function(){
    Route::get('world-maps/{worldMap}/editor',[AdminWorldMapController::class,'editor'])->name('world-maps.editor');
    Route::resource('world-maps',AdminWorldMapController::class)->parameters(['world-maps'=>'worldMap']);
    Route::post('world-maps/{worldMap}/areas',[AdminWorldMapAreaController::class,'store'])->name('world-maps.areas.store');
    Route::put('world-maps/{worldMap}/areas/{worldMapArea}',[AdminWorldMapAreaController::class,'update'])->name('world-maps.areas.update');
    Route::patch('world-maps/{worldMap}/areas/{worldMapArea}/activate',[AdminWorldMapAreaController::class,'activate'])->name('world-maps.areas.activate');
    Route::patch('world-maps/{worldMap}/areas/{worldMapArea}/deactivate',[AdminWorldMapAreaController::class,'deactivate'])->name('world-maps.areas.deactivate');
    Route::resource('items',AdminItemController::class)->except('show');
    Route::resource('monsters',AdminMonsterController::class);
    Route::resource('zones',AdminZoneController::class);
    Route::get('loot',[AdminLootController::class,'index'])->name('loot.index');
    Route::post('zones/{zone}/monsters',[AdminZoneMonsterController::class,'store'])->name('zones.monsters.store');
    Route::put('zones/{zone}/monsters/{monster}',[AdminZoneMonsterController::class,'update'])->name('zones.monsters.update');
    Route::delete('zones/{zone}/monsters/{monster}',[AdminZoneMonsterController::class,'destroy'])->name('zones.monsters.destroy');
    Route::post('monsters/{monster}/loot',[AdminLootController::class,'store'])->name('monsters.loot.store');
    Route::put('monsters/{monster}/loot/{lootEntry}',[AdminLootController::class,'update'])->name('monsters.loot.update');
    Route::patch('monsters/{monster}/loot/{lootEntry}/activate',[AdminLootController::class,'activate'])->name('monsters.loot.activate');
    Route::patch('monsters/{monster}/loot/{lootEntry}/deactivate',[AdminLootController::class,'deactivate'])->name('monsters.loot.deactivate');
    Route::get('refinement',[AdminRefinementController::class,'index'])->name('refinement.index');
    Route::post('refinement',[AdminRefinementController::class,'store'])->name('refinement.store');
    Route::put('refinement/{refinementLevel}',[AdminRefinementController::class,'update'])->name('refinement.update');
    Route::patch('refinement/{refinementLevel}/activate',[AdminRefinementController::class,'activate'])->name('refinement.activate');
    Route::patch('refinement/{refinementLevel}/deactivate',[AdminRefinementController::class,'deactivate'])->name('refinement.deactivate');
    Route::delete('refinement/{refinementLevel}',[AdminRefinementController::class,'destroy'])->name('refinement.destroy');
    Route::post('refinement/{refinementLevel}/materials',[AdminRefinementController::class,'storeMaterial'])->name('refinement.materials.store');
    Route::put('refinement/{refinementLevel}/materials/{material}',[AdminRefinementController::class,'updateMaterial'])->name('refinement.materials.update');
    Route::delete('refinement/{refinementLevel}/materials/{material}',[AdminRefinementController::class,'destroyMaterial'])->name('refinement.materials.destroy');
    Route::post('refinement/stats',[AdminRefinementStatModifierController::class,'store'])->name('refinement.stats.store');
    Route::put('refinement/stats/{modifier}',[AdminRefinementStatModifierController::class,'update'])->name('refinement.stats.update');
    Route::patch('refinement/stats/{modifier}/activate',[AdminRefinementStatModifierController::class,'activate'])->name('refinement.stats.activate');
    Route::patch('refinement/stats/{modifier}/deactivate',[AdminRefinementStatModifierController::class,'deactivate'])->name('refinement.stats.deactivate');
});
