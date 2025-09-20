<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LlmController;

Route::redirect('/', '/login');

// Authentication
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// LLM proxy (CORS handled in controller). Keep outside auth for cross-origin testing.
Route::match(['post','options'], '/llm', [LlmController::class, 'proxy'])->name('llm.proxy');

// Protected pages
Route::middleware(['web','auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/entries/publish', [DashboardController::class, 'publishEntry'])->name('entries.publish');
    Route::get('/entries/fetch', [DashboardController::class, 'fetchEntry'])->name('entries.fetch');
    Route::get('/reports/daily', [DashboardController::class, 'dailyReport'])->name('reports.daily');
    Route::get('/reports/weekly', [DashboardController::class, 'weeklyReport'])->name('reports.weekly');
    Route::get('/statuses', [DashboardController::class, 'statusesByDate'])->name('statuses.date');
    Route::get('/statuses/range', [DashboardController::class, 'statusesByRange'])->name('statuses.range');

    // Team management
    Route::post('/team/users', [DashboardController::class, 'storeUser'])->name('team.users.store');
    Route::put('/team/users/{user}', [DashboardController::class, 'updateUser'])->name('team.users.update');
    Route::delete('/team/users/{user}', [DashboardController::class, 'destroyUser'])->name('team.users.destroy');
});
