<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/about', function () {
    return 'about';
});


Route::get('/healthz', function () {
    return 'healthy';
});

Route::get('/test', function () {
    return response('test', 200);
});

Route::get('/one', function () {
    return response('one', 200);
});

Route::get('/two', function () {
    return response('two', 200);
});