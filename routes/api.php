<?php

use App\Http\Controllers\CepController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\FreteController;
use Illuminate\Support\Facades\Route;

Route::post('/frete', [FreteController::class, 'calcular']);