<?php

use Illuminate\Support\Facades\Route;
use Stake\BetLookup\Http\Controllers\AdminController;
use Stake\BetLookup\Http\Controllers\BetLookupController;
use Stake\BetLookup\Http\Middleware\AuthenticateAdmin;
use Stake\BetLookup\Http\Middleware\ValidateBetId;

Route::post('/api/bet-lookup', [BetLookupController::class, 'lookup'])
    ->middleware(['throttle:bet-lookup', ValidateBetId::class]);

Route::prefix('api/admin')
    ->middleware([AuthenticateAdmin::class, 'throttle:10,1'])
    ->group(function () {
        Route::post('update-clearance',      [AdminController::class, 'updateClearance']);
        Route::get('clearance-status',       [AdminController::class, 'getStatus']);
        Route::get('clearance-credentials',  [AdminController::class, 'getCredentials']);
        Route::post('test-clearance',        [AdminController::class, 'testClearance']);
    });
