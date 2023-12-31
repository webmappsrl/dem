<?php

use App\Http\Controllers\CalculatePointElevationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::name('api.')->group(function () {
    Route::prefix('v1')->name('v1')->group(function () {
        Route::get('/elevation/{lng}/{lat}', [CalculatePointElevationController::class, 'getElevation'])->name('get-point-elevation');
    });
});
