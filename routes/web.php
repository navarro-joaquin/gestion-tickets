<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/quiosco', 'kiosk')->name('kiosk');
Route::view('/consola', 'console')->name('console');
Route::view('/pantalla', 'display')->name('display');
Route::view('/admin/ajustes', 'admin')->name('admin.settings');
