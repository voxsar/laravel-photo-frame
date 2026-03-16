<?php

use App\Http\Controllers\PhotoFrameController;
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

// Photo frame public API
Route::get('/active-frame', [PhotoFrameController::class, 'activeFrame']);
Route::get('/frame-image', [PhotoFrameController::class, 'frameImage']);
Route::post('/process-image', [PhotoFrameController::class, 'process']);
Route::get('/frame-outputs', [PhotoFrameController::class, 'outputs']);
Route::get('/frame-outputs/{frameOutput}/download/{variant}', [PhotoFrameController::class, 'download']);
