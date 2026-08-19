<?php

use App\Http\Controllers\BrowserSensorApiController;
use App\Http\Controllers\KindleSensorApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/sensors/browser/activity', BrowserSensorApiController::class)->name('api.sensors.browser.activity');
Route::post('/sensors/kindle/progress', KindleSensorApiController::class)->name('api.sensors.kindle.progress');
