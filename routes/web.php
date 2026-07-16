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
use App\Http\Controllers\CharacterInventoryController;
use App\Http\Controllers\CharacterWalletController;
use App\Http\Controllers\CharacterHuntController;
use App\Http\Controllers\CharacterHuntingSessionController;
use App\Http\Controllers\CharacterHuntRewardClaimController;
use App\Http\Controllers\CharacterEquipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', ShowLoginController::class)->name('login');
    Route::post('/login', LoginController::class);
    Route::get('/registro', ShowRegistrationController::class)->name('register');
    Route::post('/registro', RegisterController::class);
});

Route::middleware(['auth', 'game.navigation'])->group(function () {
    Route::get('/characters/create', [CharacterController::class, 'create'])->name('characters.create');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
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
    Route::get('/worlds', [WorldCatalogController::class, 'index'])->name('worlds.index');
    Route::get('/worlds/{world}', [WorldCatalogController::class, 'show'])->name('worlds.show');
    Route::get('/regions/{region}', [RegionCatalogController::class, 'show'])->name('regions.show');
    Route::get('/zones/{zone}', [ZoneCatalogController::class, 'show'])->name('zones.show');
});
