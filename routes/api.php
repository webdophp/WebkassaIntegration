<?php

use Illuminate\Support\Facades\Route;
use webdophp\WebkassaIntegration\Http\Controllers\WebkassaController;


Route::middleware(['api', 'webkassa.key'])->prefix('api/webkassa')->group(function () {
    Route::get('/ping', [WebkassaController::class, 'ping']);
    Route::get('/data', [WebkassaController::class, 'data']);
    Route::get('/confirm', [WebkassaController::class, 'confirm']);
});

