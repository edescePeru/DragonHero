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
