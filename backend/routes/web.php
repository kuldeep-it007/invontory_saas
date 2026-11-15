<?php

use App\Http\Controllers\Admin\TelescopeAccessController;
use App\Http\Middleware\OnlyCentralDomains;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| One-time Telescope sign-in for Platform_Admin (see TelescopeAccessController).
| Under /api so the existing nginx /api/ proxy routes it to Laravel.
*/
Route::get('/api/telescope-enter', [TelescopeAccessController::class, 'enter'])
    ->middleware(['signed', OnlyCentralDomains::class, 'throttle:10,1'])
    ->name('telescope.enter');
