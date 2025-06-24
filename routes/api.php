<?php

use Illuminate\Support\Facades\Route;
use webdophp\WebkassaIntegration\Http\Controllers\v1\WebkassaController;


Route::middleware(['api', 'webkassa.key'])->prefix('api/v1/webkassa')->group(function () {
    Route::get('/ping', [WebkassaController::class, 'ping']);
    Route::get('/data', [WebkassaController::class, 'data']);
    Route::get('/confirm', [WebkassaController::class, 'confirm']);
});

