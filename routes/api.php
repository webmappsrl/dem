<?php

use App\Http\Controllers\V1\CalculatePointElevationController;
use App\Http\Controllers\V1\CalculateTrackTechDataController;
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
        Route::post('/track', [CalculateTrackTechDataController::class, 'getTechData'])->name('get-track-tech-data');
        Route::post('/track3d', [CalculateTrackTechDataController::class, 'get3DData'])->name('get-track-3D-data');
        Route::post('/feature-collection/point-matrix', [CalculateTrackTechDataController::class, 'getMatrix'])->name('get-point-matrix');
    });
});
