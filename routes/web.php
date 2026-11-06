<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [LoginController::class, 'create'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('login.store');

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::view('/quiosco', 'kiosk')->name('kiosk');
Route::view('/pantalla', 'display')->name('display');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::view('/consola', 'console')
        ->middleware('role:admin,supervisor,operator')
        ->name('console');

    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function (): void {
            Route::view('/', 'admin')->name('dashboard');
            Route::view('/ajustes', 'admin')->name('settings');
            Route::view('/prioridades', 'admin')->name('priorities');
            Route::view('/servicios', 'admin')->name('services');
            Route::view('/simulador', 'admin')->name('simulator');
        });
});
