<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ShowLoginController;
use App\Http\Controllers\Auth\ShowRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CharacterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', ShowLoginController::class)->name('login');
    Route::post('/login', LoginController::class);
    Route::get('/registro', ShowRegistrationController::class)->name('register');
    Route::post('/registro', RegisterController::class);
});

Route::middleware('auth')->group(function () {
    Route::get('/characters/create', [CharacterController::class, 'create'])->name('characters.create');
    Route::post('/characters', [CharacterController::class, 'store'])->name('characters.store');
    Route::get('/characters/{character}', [CharacterController::class, 'show'])->name('characters.show');

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('character.required')
        ->name('dashboard');
    Route::get('/inventory.html', [DashboardController::class, 'inventory']);
    Route::get('/reports.html', [DashboardController::class, 'reports']);
    Route::get('/create-product.html', [DashboardController::class, 'createProduct']);
    Route::get('/docs.html', [DashboardController::class, 'docs']);
    Route::post('/logout', LogoutController::class)->name('logout');
});
