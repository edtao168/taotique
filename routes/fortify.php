<?php

use Laravel\Fortify\Features;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use Illuminate\Support\Facades\Route;

// 認證路由...
if (Features::enabled(Features::registration())) {
    Route::get('/register', [RegisteredUserController::class, 'create'])
        ->middleware(['guest'])
        ->name('register');
}