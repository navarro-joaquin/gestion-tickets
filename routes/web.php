<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/quiosco', 'kiosk')->name('kiosk');
Route::view('/consola', 'console')->name('console');
