<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/admin/{any?}', function () {
    return view('admin.app');
})->where('any', '.*');

Route::get('/admin/events/{event}/rekap/layar-penuh', function () {
    return view('admin.app');
})->where('event', '[0-9]+');
