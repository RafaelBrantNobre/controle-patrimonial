<?php

use App\Http\Controllers\LoginExemploController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'login')->name('login');
Route::redirect('/login', '/');
Route::post('/login', [LoginExemploController::class, 'store'])->name('login.exemplo');
