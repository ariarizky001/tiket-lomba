<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::view('/admin/{any?}', 'admin.app')->where('any', '.*');

Route::view('/admin/events/{event}/rekap/layar-penuh', 'admin.app')->where('event', '[0-9]+');
