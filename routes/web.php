<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\HomeController;

// API routes
Route::prefix('api')->group(function () {
    require __DIR__.'/api.php';
});

// SPA catch-all route
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/{any}', [HomeController::class, 'index'])->where('any', '.*');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
