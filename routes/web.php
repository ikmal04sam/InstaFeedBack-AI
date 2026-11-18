<?php

use App\Http\Controllers\EssayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('homePage');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [EssayController::class, 'index'])->name('dashboard');
    Route::post('essays', [EssayController::class, 'store'])->name('essays.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
