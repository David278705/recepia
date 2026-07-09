<?php

use Illuminate\Support\Facades\Route;

// Named "login": único login de la app (Vue Router lo renderiza). Laravel usa
// route('login') internamente para redirigir invitados no autenticados en
// peticiones que no envían Accept: application/json.
Route::view('login', 'app')->name('login');

Route::view('/{any}', 'app')->where('any', '.*');
