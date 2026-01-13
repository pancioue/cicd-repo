<?php

use Illuminate\Support\Facades\Route;
use App\Support\TaxCalculator;
use App\Support\UsernameNormalizer;

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


Route::get('/two', function () {
    return response('two', 200);
});

Route::get('/three', function () {
    return response('three', 200);
});

Route::get('/tools/tax', function () {
    $net = (int) request()->query('net', 0);
    $rate = (float) request()->query('rate', 0);

    $calculator = new TaxCalculator();

    return response()->json([
        'net_cents' => $net,
        'rate_percent' => $rate,
        'tax_cents' => $calculator->calculateTax($net, $rate),
        'gross_cents' => $calculator->calculateGross($net, $rate),
    ]);
});

Route::get('/tools/normalize', function () {
    $input = (string) request()->query('input', '');

    $normalizer = new UsernameNormalizer();

    return response()->json([
        'input' => $input,
        'normalized' => $normalizer->normalize($input),
    ]);
});
