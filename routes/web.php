<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'Coturnos & Cia API',
        'status' => 'online',
        'version' => '1.0'
    ]);
});
