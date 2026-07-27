<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\WordController;
use Illuminate\Support\Facades\Route;

// --- Public ------------------------------------------------------------------
Route::post('/auth/google', [AuthController::class, 'google']);
Route::get('/health', fn () => response()->json(['ok' => true]));

// --- Authenticated (Sanctum bearer token) ------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']);
    Route::post('/collections/generate', [CollectionController::class, 'generate']);
    Route::get('/collections/{collection}', [CollectionController::class, 'show']);
    Route::put('/collections/{collection}', [CollectionController::class, 'update']);
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy']);

    Route::post('/collections/{collection}/words', [WordController::class, 'store']);
    Route::put('/collections/{collection}/words/{word}', [WordController::class, 'update']);
    Route::delete('/collections/{collection}/words/{word}', [WordController::class, 'destroy']);

    Route::get('/reviews/due', [ReviewController::class, 'due']);
    Route::post('/reviews/{word}/answer', [ReviewController::class, 'answer']);

    Route::get('/stats', [StatsController::class, 'index']);

    Route::get('/ai/jobs/{aiJob}', [AiController::class, 'jobStatus']);
    Route::post('/ai/check', [AiController::class, 'check']);
});
