<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RollerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('rollers/{roller:slug}', [RollerController::class, 'show'])
    ->name('rollers.show');
Route::post('rollers/{roller:slug}/roll', [RollerController::class, 'roll'])
    ->name('rollers.roll');

require __DIR__.'/settings.php';
