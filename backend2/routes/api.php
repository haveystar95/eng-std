<?php

use App\Http\Controllers\HealthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// КАКАЯ СБОРКА ОТВЕЧАЕТ (наряд DAY-GATE-1, Ч.0.4). Открытый и без состояния: строка версии внизу
// вкладки «План» спрашивает его и до логина. Модульные роуты живут в своих провайдерах; этот —
// приложенческий, у него нет модуля и быть не должно.
Route::get('/v1/health', HealthController::class);
